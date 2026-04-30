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

Les comptes ci-dessous sont prevus pour tester le parcours principal du site :

| Role | Email | Mot de passe |
|---|---|---|
| Admin | `admin.demo@portfolium.fr` | `DemoStage2026!` |
| Entreprise | `entreprise.demo@portfolium.fr` | `DemoStage2026!` |
| Etudiant | `etudiant.demo@portfolium.fr` | `DemoStage2026!` |


## Donnees de demo prevues sur le site

Le jeu de donnees de demonstration :

- une entreprise de demo `TechNova` ;
- sept offres de stage publiees par `TechNova` ;
- deux missions associees a chaque offre ;
- plusieurs candidatures d'exemple pour l'etudiant de demo ;
- une candidature avec statut `proposition envoyée`, prete a etre acceptee ou refusee par l'etudiant ;
- deux candidatures avec statut `en attente`, visibles depuis l'espace entreprise ;
- une candidature avec statut `refusée par l'étudiant`, utile pour montrer l'historique ;
- une convention de stage preparee cote entreprise pour la proposition envoyee ;
- une remarque de suivi.

Cela permet de montrer rapidement plusieurs parcours sans devoir tout ressaisir avant la soutenance.

### Offres creees pour la demo

- `Developpeur Web Full Stack`
- `Analyste Data Junior`
- `Assistant Cybersecurite`
- `Ingenieur DevOps Cloud`
- `UX UI Designer Produit`
- `Developpeur Mobile Flutter`
- `Charge de Projet IA`

## Parcours d'une demande de stage

1. L'entreprise se connecte avec `entreprise.demo@portfolium.fr`.
2. Elle depose une offre depuis son espace entreprise. L'offre est enregistree dans `stages` avec le statut `ouverte`.
3. L'etudiant se connecte avec `etudiant.demo@portfolium.fr` et consulte les offres disponibles depuis son accueil.
4. L'etudiant ouvre une offre puis depose sa candidature avec un CV et une lettre de motivation. Une ligne est creee dans `candidatures` avec le statut `en attente`.
5. L'entreprise consulte `Mes offres`, ouvre les candidatures d'une offre, puis envoie une proposition a l'etudiant retenu. La candidature passe a `proposition envoyée` et une convention est preparee cote entreprise.
6. L'etudiant va dans `Mes Candidatures` et accepte ou refuse la proposition.
7. En cas d'acceptation, le stage passe a `en cours`, l'etudiant est rattache au stage, puis il peut deposer sa convention.
8. L'entreprise puis l'admin peuvent suivre et valider la convention.
9. A la fin du stage, l'admin archive le dossier depuis `Archives`. Les stages archives restent consultables meme si le compte etudiant est supprime ensuite.

## Parcours de test conseilles

### Admin

Compte :
`admin.demo@portfolium.fr`

Ce que vous pouvez verifier :

- acces au tableau de bord admin ;
- consultation de la liste des comptes ;
- modification du role d'un utilisateur ;
- suppression d'un compte etudiant ;
- verification que les stages attribues a un etudiant supprime restent en `Archives`.

### Entreprise

Compte :
`entreprise.demo@portfolium.fr`

Ce que vous pouvez verifier :

- consultation de `Mes offres` avec les sept offres TechNova ;
- visualisation des candidatures par offre ;
- envoi d'une proposition a un etudiant ;
- ajout de missions ;
- ajout de remarques sur le stage.

### Etudiant

Compte :
`etudiant.demo@portfolium.fr`

Ce que vous pouvez verifier :

- consultation des offres ;
- visualisation des missions ;
- suivi des candidatures ;
- presence d'une proposition de stage ;
- acceptation ou refus du stage ;
- depot de convention apres acceptation ;
- possibilite de postuler pendant la presentation a une offre sans candidature existante, par exemple `Developpeur Mobile Flutter` ou `UX UI Designer Produit`.

## Remarques

- l'authentification et la double authentification passent par Supabase ;
- les comptes de demo utilisent tous le meme mot de passe pour simplifier la soutenance ;
- si vous activez la MFA sur un compte de demo, il faudra ensuite utiliser le code TOTP a chaque connexion ;
- les espaces `tuteur` et `jury` existent dans l'application mais ne sont pas fonctionnel, donc le scenario de demonstration principal repose uniquement sur les roles `admin`, `entreprise` et `etudiant`.
