<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once __DIR__ . '/../../../../core/php/core.inc.php';

class jourtempo extends eqLogic {

  const API = 'https://jourtempo.fr/api/v1/';

  /* Commandes info : logicalId => [nom, sous-type, unité, option qui l'active] */
  const INFOS = array(
    'tempo_aujourdhui' => array("Tempo aujourd'hui", 'string', '', 'tempo'),
    'tempo_demain' => array('Tempo demain', 'string', '', 'tempo'),
    'tempo_demain_rouge' => array('Demain rouge', 'binary', '', 'tempo'),
    'tempo_demain_blanc' => array('Demain blanc', 'binary', '', 'tempo'),
    'tempo_rouges_restants' => array('Jours rouges restants', 'numeric', 'j', 'tempo'),
    'tempo_blancs_restants' => array('Jours blancs restants', 'numeric', 'j', 'tempo'),
    'tempo_bleus_restants' => array('Jours bleus restants', 'numeric', 'j', 'tempo'),
    'tempo_rouge_j2' => array('Rouge J+2 (historique)', 'numeric', '%', 'tempo'),
    'tempo_rouge_j3' => array('Rouge J+3 (historique)', 'numeric', '%', 'tempo'),
    'ejp_aujourdhui' => array("EJP aujourd'hui", 'string', '', 'ejp'),
    'ejp_demain' => array('EJP demain', 'string', '', 'ejp'),
    'ejp_demain_actif' => array('Demain jour EJP', 'binary', '', 'ejp'),
    'ejp_restants' => array('Jours EJP restants', 'numeric', 'j', 'ejp'),
    'maj' => array('Dernière mise à jour', 'string', '', ''),
  );

  /* Toutes les 15 minutes : la couleur de demain tombe vers 11 h, le jour EJP entre 13 h et 15 h (parfois la nuit). */
  public static function cron15() {
    $donnees = null;
    foreach (eqLogic::byType(__CLASS__, true) as $eqLogic) {
      if ($donnees === null) {
        $donnees = self::lireApi();
      }
      $eqLogic->appliquer($donnees);
    }
  }

  /* Une fois par jour : la part historique de jours rouges, date par date, depuis 2014 (fichier CSV publié par jourtempo.fr). */
  public static function cronDaily() {
    self::statistiques(true);
  }

  public static function telecharger($fichier) {
    $http = new com_http(self::API . $fichier);
    $http->setHeader(array('User-Agent: Jeedom-jourtempo/' . self::version()));
    try {
      return $http->exec(15, 2);
    } catch (Exception $e) {
      log::add(__CLASS__, 'warning', 'Lecture de ' . $fichier . ' impossible : ' . $e->getMessage());
      return null;
    }
  }

  public static function version() {
    $info = json_decode(@file_get_contents(__DIR__ . '/../../plugin_info/info.json'), true);
    return is_array($info) && isset($info['pluginVersion']) ? $info['pluginVersion'] : '1';
  }

  /* Lit tempo.json et ejp.json. En cas d'échec, reprend la dernière réponse valide (gardée 7 jours) :
     appliquer() la passera à « inconnu » une fois sa date perime_apres dépassée. */
  public static function lireApi() {
    $donnees = array('frais' => false);
    foreach (array('tempo' => 'tempo.json', 'ejp' => 'ejp.json') as $cle => $fichier) {
      $brut = self::telecharger($fichier);
      $json = $brut ? json_decode($brut, true) : null;
      if (is_array($json) && isset($json['api'], $json['aujourdhui'], $json['demain']) && $json['api'] == 'v1') {
        cache::set('jourtempo::dernier::' . $cle, $json, 7 * 86400);
        $donnees['frais'] = true;
      } else {
        $json = cache::byKey('jourtempo::dernier::' . $cle)->getValue(null);
      }
      $donnees[$cle] = is_array($json) ? $json : null;
    }
    return $donnees;
  }

  /* Vrai si le fichier manque ou si sa date perime_apres est dépassée : la donnée n'est plus vraie. */
  public static function estPerime($fichier) {
    if (!is_array($fichier) || empty($fichier['perime_apres'])) {
      return true;
    }
    $limite = strtotime($fichier['perime_apres']);
    return $limite === false || time() > $limite;
  }

  public static function code($fichier, $jour) {
    return isset($fichier[$jour]['code']) ? $fichier[$jour]['code'] : 'inconnu';
  }

  /* { 'MM-JJ' => part de rouge (0 à 1) }, gardée 26 h en cache */
  public static function statistiques($forcer = false) {
    $cache = cache::byKey('jourtempo::statistiques');
    $stats = $cache->getValue(null);
    if (!$forcer && is_array($stats) && count($stats) > 300) {
      return $stats;
    }
    $brut = self::telecharger('tempo-statistiques-jour.csv');
    if (!$brut) {
      return is_array($stats) ? $stats : array();
    }
    $stats = array();
    $lignes = explode("\n", trim($brut));
    $entete = str_getcsv(array_shift($lignes));
    $iJour = array_search('jour', $entete);
    $iRouge = array_search('part_rouge', $entete);
    foreach ($lignes as $ligne) {
      $c = str_getcsv($ligne);
      if (isset($c[$iJour], $c[$iRouge])) {
        $stats[$c[$iJour]] = floatval($c[$iRouge]);
      }
    }
    cache::set('jourtempo::statistiques', $stats, 26 * 3600);
    return $stats;
  }

  /* Part historique de jours rouges pour une date : 0 hors du 1er novembre - 31 mars et le week-end (jamais de rouge). */
  public static function partRouge($date) {
    $mois = intval(date('n', $date));
    if (($mois > 3 && $mois < 11) || intval(date('N', $date)) >= 6) {
      return 0;
    }
    $stats = self::statistiques();
    $cle = date('m-d', $date);
    return isset($stats[$cle]) ? round(100 * $stats[$cle]) : null;
  }

  public function appliquer($donnees) {
    $tempo = isset($donnees['tempo']) ? $donnees['tempo'] : null;
    $ejp = isset($donnees['ejp']) ? $donnees['ejp'] : null;
    if ($this->getConfiguration('suivre_tempo', 1)) {
      if (self::estPerime($tempo)) {
        // Panne prolongée : jamais une ancienne couleur présentée comme actuelle.
        $this->checkAndUpdateCmd('tempo_aujourdhui', 'inconnu');
        $this->checkAndUpdateCmd('tempo_demain', 'inconnu');
        $this->checkAndUpdateCmd('tempo_demain_rouge', 0);
        $this->checkAndUpdateCmd('tempo_demain_blanc', 0);
      } else {
        $demain = self::code($tempo, 'demain');
        $this->checkAndUpdateCmd('tempo_aujourdhui', self::code($tempo, 'aujourdhui'));
        $this->checkAndUpdateCmd('tempo_demain', $demain);
        $this->checkAndUpdateCmd('tempo_demain_rouge', $demain == 'rouge' ? 1 : 0);
        $this->checkAndUpdateCmd('tempo_demain_blanc', $demain == 'blanc' ? 1 : 0);
        foreach (array('rouge' => 'rouges', 'blanc' => 'blancs', 'bleu' => 'bleus') as $couleur => $pluriel) {
          if (isset($tempo['saison']['compteurs'][$couleur]['restants'])) {
            $this->checkAndUpdateCmd('tempo_' . $pluriel . '_restants', $tempo['saison']['compteurs'][$couleur]['restants']);
          }
        }
        if (!empty($tempo['aujourdhui']['date'])) {
          $base = strtotime($tempo['aujourdhui']['date'] . ' 12:00:00');
          foreach (array(2, 3) as $j) {
            $part = self::partRouge($base + $j * 86400);
            if ($part !== null) {
              $this->checkAndUpdateCmd('tempo_rouge_j' . $j, $part);
            }
          }
        }
      }
    }
    if ($this->getConfiguration('suivre_ejp', 1)) {
      if (self::estPerime($ejp)) {
        $this->checkAndUpdateCmd('ejp_aujourdhui', 'inconnu');
        $this->checkAndUpdateCmd('ejp_demain', 'inconnu');
        $this->checkAndUpdateCmd('ejp_demain_actif', 0);
      } else {
        $demain = self::code($ejp, 'demain');
        $this->checkAndUpdateCmd('ejp_aujourdhui', self::code($ejp, 'aujourdhui'));
        $this->checkAndUpdateCmd('ejp_demain', $demain);
        $this->checkAndUpdateCmd('ejp_demain_actif', $demain == 'ejp' ? 1 : 0);
        if (isset($ejp['saison']['restants'])) {
          $this->checkAndUpdateCmd('ejp_restants', $ejp['saison']['restants']);
        }
      }
    }
    if (!empty($donnees['frais'])) {
      $this->checkAndUpdateCmd('maj', date('Y-m-d H:i'));
    }
  }

  public function postSave() {
    $ordre = 0;
    foreach (self::INFOS as $logicalId => $def) {
      $cmd = $this->getCmd('info', $logicalId);
      $actif = $def[3] == '' || $this->getConfiguration('suivre_' . $def[3], 1);
      if (!$actif) {
        if (is_object($cmd)) {
          $cmd->remove();
        }
        continue;
      }
      if (!is_object($cmd)) {
        $cmd = new jourtempoCmd();
        $cmd->setName(__($def[0], __FILE__));
        $cmd->setLogicalId($logicalId);
        $cmd->setEqLogic_id($this->getId());
        $cmd->setType('info');
        $cmd->setSubType($def[1]);
        $cmd->setUnite($def[2]);
        $cmd->setIsHistorized($def[1] == 'string' ? 0 : 1);
        $cmd->setOrder($ordre);
      }
      $cmd->save();
      $ordre++;
    }
    $refresh = $this->getCmd('action', 'refresh');
    if (!is_object($refresh)) {
      $refresh = new jourtempoCmd();
      $refresh->setName(__('Rafraîchir', __FILE__));
      $refresh->setLogicalId('refresh');
      $refresh->setEqLogic_id($this->getId());
      $refresh->setType('action');
      $refresh->setSubType('other');
      $refresh->setOrder($ordre);
      $refresh->save();
    }
    if ($this->getIsEnable()) {
      $this->appliquer(self::lireApi());
    }
  }
}

class jourtempoCmd extends cmd {

  public function dontRemoveCmd() {
    return true;
  }

  public function execute($_options = array()) {
    if ($this->getLogicalId() == 'refresh') {
      $this->getEqLogic()->appliquer(jourtempo::lireApi());
    }
  }
}
