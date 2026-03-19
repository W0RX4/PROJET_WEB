<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <form method="post">
        <input type="text" name="username" placeholder="Username" required><br>
        <input type="email" name="email" placeholder="Email" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <select name="type">
            <option value="etudiant">Etudiant</option>
            <option value="entreprise">Entreprise</option>
            <option value="jury">jury</option>
        </select><br>
        <button type="submit">Submit</button><br>
    </form>
</body>
</html>
<?php
    require_once __DIR__ . '/vendor/autoload.php';

    use Dotenv\Dotenv;
    use Supabase\Client\Functions;

    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();

    $url = $_ENV['SUPABASE_URL'] ?? 'URL_MANQUANTE';
    $api = $_ENV['SUPABASE_KEY'] ?? 'KEY_MANQUANTE';

    $client = new Functions($url, $api);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $allowedTypes = ['etudiant', 'entreprise', 'jury'];
        $type = $_POST['type'] ?? '';

        if (!in_array($type, $allowedTypes, true)) {
            exit('Type invalide');
        }

        $newUser = [
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'password' => password_hash($_POST['password'], PASSWORD_BCRYPT),
            'type' => $type
        ];

        $insertResult = $client->postData('users', $newUser);

        // Récupérer toutes les lignes de la table 'users'
        $data = $client->getAllData('users');

        echo "<pre>";
        print_r([
            'insert_result' => $insertResult,
            'users' => $data,
        ]);
        echo "</pre>";
    }
?>