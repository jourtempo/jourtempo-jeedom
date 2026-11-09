# Plugin Jour Tempo

Ce plugin récupère la couleur Tempo et le jour EJP d'aujourd'hui et de demain depuis l'API gratuite de [jourtempo.fr](https://jourtempo.fr/api/), sans clé ni compte. Il sert à déclencher vos scénarios : couper le ballon d'eau chaude un jour rouge, baisser le chauffage pendant un jour EJP, vous prévenir la veille.

## Installation et configuration

1. Installez le plugin depuis le Market, puis activez-le. Aucun réglage global n'est nécessaire.
2. Dans **Plugins > Énergie > Jour Tempo**, cliquez sur **Ajouter** et donnez un nom à l'équipement.
3. Cochez **Tempo**, **EJP** ou les deux selon votre contrat, puis **Sauvegardez**. Les commandes sont créées et remplies aussitôt.

## Commandes

| Commande | Type | Valeurs |
| --- | --- | --- |
| Tempo aujourd'hui | info texte | `bleu`, `blanc`, `rouge` (`inconnu` si la donnée est périmée) |
| Tempo demain | info texte | `bleu`, `blanc`, `rouge`, ou `inconnu` avant l'annonce (vers 11 h) |
| Demain rouge / Demain blanc | info binaire | 1 si demain est rouge (ou blanc) |
| Jours rouges, blancs, bleus restants | info numérique | jours restants dans la saison en cours |
| Rouge J+2 / Rouge J+3 (historique) | info numérique (%) | part des saisons depuis 2014 où ce jour du calendrier était rouge ; 0 le week-end et d'avril à octobre |
| EJP aujourd'hui / EJP demain | info texte | `ejp`, `normal`, `non-verifie` (pas encore publié par EDF), `hors-saison`, ou `inconnu` si la donnée est périmée |
| Demain jour EJP | info binaire | 1 si demain est un jour EJP |
| Jours EJP restants | info numérique | sur les 22 de la saison |
| Dernière mise à jour | info texte | date et heure de la dernière lecture réussie de l'API |
| Rafraîchir | action | relit l'API tout de suite |

« Rouge J+2 » et « Rouge J+3 » ne sont pas des prévisions : ce sont des fréquences observées depuis 2014, utiles pour savoir si la période est propice aux jours rouges.

## Fréquence de mise à jour

Le plugin lit l'API toutes les 15 minutes. La couleur Tempo du lendemain est publiée par RTE en fin de matinée ; le jour EJP du lendemain par EDF entre 13 h et 15 h, parfois dans la nuit un jour EJP. Un jour EJP s'applique de 7 h à 1 h le lendemain.

## En cas de panne

Si l'API ne répond pas, le plugin garde la dernière réponse reçue. Chaque fichier de l'API porte une date de validité (`perime_apres`) : une fois cette date passée, les couleurs et les jours EJP passent à `inconnu` et les commandes binaires à 0, plutôt que de garder une ancienne couleur. Un scénario ne se déclenche donc jamais sur une donnée périmée. Les compteurs de jours restants gardent leur dernière valeur.

## Exemples de scénarios

- **Prévenir la veille d'un jour rouge** : déclencheur `#[Maison][Jour Tempo][Demain rouge]# == 1`, action : notification.
- **Délester un jour EJP** : déclencheur `#[Maison][Jour Tempo][EJP aujourd'hui]# == "ejp"`, à 7 h, action : baisser la consigne de chauffage.

## Sources

Tempo : données officielles de RTE ; EJP : calendrier publié par EDF. Les données sont relevées et publiées par jourtempo.fr ; en cas de doute, les pages d'EDF font foi.
