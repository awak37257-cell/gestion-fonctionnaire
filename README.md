# 🏛️ Application de Gestion des Fonctionnaires — Ministère des Affaires Étrangères

Système d'information et de gestion des ressources humaines (SIGRH) développé pour centraliser le répertoire des agents, gérer les profilages, les compétences, les diplômes ainsi que les mutations internes et consulaires.

---

## 🚀 Fonctionnalités Principales

- **Répertoire Officiel des Agents** : Consultation, filtrage avancé par organisation / direction et recherche multi-critères (matricule, nom, grade, ville d'affectation).
- **Gestion des Profils & Compétences** : Ajout et valorisation des compétences techniques avec justificatifs (PDF/Image).
- **Gestion des Diplômes** : Enregistrement du niveau d'études, de l'année d'obtention et des justificatifs d'authentification.
- **Suivi des Mutations** : Historique et affectation administrative des agents dans les différentes structures et représentations diplomatiques.
- **Sécurité & Rôles** : Gestion fine des accès (Administrateurs, Gestionnaires locaux, Consultants).

---

## 🛠️ Technologies Utilisées

- **Langage Backend** : PHP 8.x (Architecture procédurale / PDO)
- **Base de Données** : MySQL / MariaDB (Méthodologie MERISE)
- **Frontend** : HTML5, CSS3 (Modern Glassmorphism & Card UI), JavaScript
- **Polices & Icônes** : Google Fonts (Poppins), FontAwesome 6
- **Environnement de développement** : WampServer

---

## 📂 Structure du Projet

```text
fonctionnaire_ministere/
├── actions/             # Scripts de traitement PHP (POST/AJAX)
├── config.php          # Configuration PDO de la base de données
├── database/           # Scripts d'export SQL de la base de données
│   └── schema.sql
├── views/              # Vues et composants HTML/PHP
│   └── includes/       # Navigation (navbar) et éléments réutilisables
├── uploads/            # Dossier de stockage des pièces justificatives & photos
├── index.php           # Page d'accueil / Connexion
└── README.md