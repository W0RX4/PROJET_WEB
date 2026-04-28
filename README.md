# CyTech Internship Hub

Plateforme web de gestion et de suivi des stages developpee en `PHP`, `Slim` et `Supabase`.

## Acces rapide

Pour tester directement le site en ligne :

`https://portfolium.fr/`

## Connexion

Depuis la page d'accueil, vous pouvez :

- vous connecter via `Se connecter` ;
- activer ensuite la double authentification depuis l'espace `Securite`.

La connexion repose sur `Supabase Auth`. Si un facteur MFA est deja actif sur le compte, une verification supplementaire est demandee apres le mot de passe.

Pour la demonstration, il faut utiliser uniquement les comptes de test deja prepares ci-dessous.

## Comptes de demo pour le professeur

Les comptes ci-dessous sont prevus pour tester chaque role du site :

| Role | Email | Mot de passe |
|---|---|---|
| Admin | `admin.demo@portfolium.fr` | `DemoStage2026!` |
| Entreprise | `entreprise.demo@portfolium.fr` | `DemoStage2026!` |
| Etudiant 1 | `etudiant.demo@portfolium.fr` | `DemoStage2026!` |
| Etudiant 2 | `etudiant2.demo@portfolium.fr` | `DemoStage2026!` |
| Tuteur | `tuteur.demo@portfolium.fr` | `DemoStage2026!` |
| Jury | `jury.demo@portfolium.fr` | `DemoStage2026!` |

## Donnees de demo prevues sur le site

Le jeu de donnees de demonstration cree :

- une entreprise de demo `TechNova` ;
- deux offres de stage ;
- plusieurs missions associees aux offres ;
- une candidature en attente ;
- une candidature avec statut `proposition envoyee` ;
- une convention de stage preparee cote entreprise ;
- une remarque de suivi.

Cela permet de montrer rapidement plusieurs parcours sans devoir tout ressaisir avant la soutenance.

## Parcours de test conseilles

### Admin

Compte :
`admin.demo@portfolium.fr`

Ce que vous pouvez verifier :

- acces au tableau de bord admin ;
- consultation de la liste des comptes ;
- modification du role d'un utilisateur ;
- suppression d'un compte.

### Entreprise

Compte :
`entreprise.demo@portfolium.fr`

Ce que vous pouvez verifier :

- ajout d'une nouvelle offre ;
- consultation de `Mes offres` ;
- visualisation des candidatures par offre ;
- envoi d'une proposition a un etudiant ;
- ajout de missions ;
- ajout de remarques sur le stage.

### Etudiant

Compte principal :
`etudiant.demo@portfolium.fr`

Ce que vous pouvez verifier :

- consultation des offres ;
- visualisation des missions ;
- suivi des candidatures ;
- presence d'une proposition de stage ;
- acceptation ou refus du stage ;
- depot de convention apres acceptation.

Compte secondaire :
`etudiant2.demo@portfolium.fr`

Ce que vous pouvez verifier :

- consultation des offres ;
- presence d'une candidature en attente ;
- possibilite de postuler a une autre offre.

### Tuteur

Compte :
`tuteur.demo@portfolium.fr`

Ce que vous pouvez verifier :

- acces a l'espace tuteur ;
- affichage de la page dediee.

### Jury

Compte :
`jury.demo@portfolium.fr`

Ce que vous pouvez verifier :

- acces a l'espace jury ;
- affichage de la page dediee.

## Structure utile

- [index.php](/home/cytech/Desktop/ING1/S2/DEV_WEB/PROJET_WEB/index.php) : point d'entree et routes Slim
- [connection/login.php](/home/cytech/Desktop/ING1/S2/DEV_WEB/PROJET_WEB/connection/login.php) : page de connexion
- [app/account/security.php](/home/cytech/Desktop/ING1/S2/DEV_WEB/PROJET_WEB/app/account/security.php) : gestion MFA

## Remarques

- l'authentification et la double authentification passent par Supabase ;
- les comptes de demo utilisent tous le meme mot de passe pour simplifier la soutenance ;
- si vous activez la MFA sur un compte de demo, il faudra ensuite utiliser le code TOTP a chaque connexion ;
- les comptes et donnees de demonstration sont deja presents en base ;
- les espaces `tuteur` et `jury` sont accessibles, mais restent plus legers fonctionnellement que les espaces `admin`, `entreprise` et `etudiant`.
