<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création d'un compte</title>
</head>
<body>
    <form method="post" action="verifNewUser.php">
        <input type="text" name="username" placeholder="Username" required><br>
        <input type="email" name="email" placeholder="Email" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <select name="type">
            <option value="etudiant">Etudiant</option>
            <option value="entreprise">Entreprise</option>
            <option value="jury">Jury</option>
        </select><br>
        <button type="submit">Créer un compte</button><br>
    </form>
</body>
</html>