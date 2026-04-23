# CyTech Internship Hub

## Description du projet
Plateforme web de gestion des stages pour CyTech Paris.

## Fonctionnalités principales
- **Authentification** : Connexion geree par Supabase Auth avec MFA/TOTP configurable
- **Gestion des utilisateurs** : Interface pour Admin, Étudiants, Entreprises, Tuteurs et Jury
- **Dépôt d'offres** : Les entreprises peuvent déposer des offres de stage
- **Suivi des stages** : Tableau de bord pour le suivi des candidatures et des stages en cours
- **Validation** : Interface de validation par les tuteurs et le jury
- **Gestion documentaire** : Upload et stockage des rapports de stage

## Technologies utilisées
- **Backend** : PHP 8+ avec Slim Framework
- **Base de données** : Supabase (PostgreSQL)
- **Frontend** : HTML, CSS, JavaScript
- **Authentification** : Supabase Auth + MFA (TOTP)

## Structure du projet
```
cytech-internship-hub/
├── public/                 # Point d'entrée et ressources publiques
├── src/                    # Code source de l'application
├── templates/              # Vues pour chaque type d'utilisateur
├── config/                 # Configuration et clés API
├── storage/                # Stockage des fichiers et logs
└── docs/                   # Documentation et gestion de projet
```

## Installation

### Prérequis
- PHP 8.0 ou supérieur
- Composer
- Compte Supabase

### Étapes d'installation
1. Cloner le dépôt :
   ```bash
   git clone https://github.com/votre-username/cytech-internship-hub.git
   cd cytech-internship-hub
   ```

2. Installer les dépendances :
   ```bash
   composer install
   ```

3. Configurer Supabase :
   - Creer un fichier `.env` a la racine
   - Ajouter vos cles API Supabase
   - Renseigner aussi `SUPABASE_ANON_KEY` si vous voulez activer l enrôlement MFA directement depuis l interface web
   - Activer les options voulues dans `Authentication` depuis le dashboard Supabase

4. Lancer le serveur de développement :
   ```bash
   php -S localhost:8000 -t public
   ```

5. Accéder à l'application : `http://localhost:8000`

## Utilisation
- **Admin** : Gestion complète de la plateforme
- **Étudiant** : Consultation des offres et dépôt de candidatures
- **Entreprise** : Dépôt d'offres et suivi des candidatures
- **Tuteur** : Validation et suivi des étudiants
- **Jury** : Consultation et validation finale

## Authentification et MFA
- Les comptes sont crees dans `auth.users` via Supabase Auth.
- Le profil metier de l application reste stocke dans la table `public.users`.
- La page `Securite` permet d enroler ou supprimer un facteur TOTP sans passer par PHPMailer.
- Les anciens comptes deja presents dans `public.users` sont migres automatiquement vers Supabase Auth lors de leur prochaine connexion reussie.


## Auteurs
- [Votre nom]
- [Noms des membres de l'équipe]
