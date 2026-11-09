# Plugin Jeedom Jour Tempo

Couleur Tempo et jour EJP d'aujourd'hui et de demain dans Jeedom, sans clé ni compte, depuis l'API gratuite de [jourtempo.fr](https://jourtempo.fr/api/).

- couleur Tempo d'aujourd'hui et de demain, commandes binaires « Demain rouge » et « Demain blanc » ;
- jours bleus, blancs et rouges restants dans la saison ;
- jour EJP d'aujourd'hui et de demain, jours EJP restants sur 22 ;
- part historique de jours rouges à J+2 et J+3 (fréquence observée depuis 2014, pas une prévision) ;
- mise à jour toutes les 15 minutes ; en cas de panne prolongée, les valeurs passent à `inconnu` plutôt que de garder une ancienne couleur.

Documentation : [docs/fr_FR/index.md](docs/fr_FR/index.md). Données : RTE (Tempo) et calendrier publié par EDF (EJP), diffusées par [Jour Tempo](https://jourtempo.fr/). Service indépendant, sans lien avec EDF ni RTE.

Licence GPL v2, comme le cœur de Jeedom.
