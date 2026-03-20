<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <?php
        require_once '../../includes/header.php';

        if($_SESSION['type'] !== 'etudiant'){
            header('Location: ../../connection/login.php');
            exit;
        }

        require_once __DIR__ . '/../../vendor/autoload.php';

        use Dotenv\Dotenv;
        use Supabase\Client\Functions;

        $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
        $dotenv->safeLoad();

        $client = new Functions($_ENV['SUPABASE_URL'] ?? '', $_ENV['SUPABASE_KEY'] ?? '');

        ?>
        <form action="enregistrerCandidature.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="stage_id" value="<?php echo htmlspecialchars($_GET['stage_id'] ?? ''); ?>">
            
            <div class="form-group">
                <label for="student_name">Votre nom</label>
                <input type="text" id="student_name" name="student_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="CV">Votre CV (PDF)</label>
                <input type="file" id="CV" name="CV" class="form-control" accept=".pdf" required>
            </div>
            <div class="form-group">
                <label for="cover_letter">Votre lettre de motivation (PDF)</label>
                <input type="file" id="cover_letter" name="cover_letter" class="form-control" accept=".pdf" required>
            </div>
            
            <button type="submit" class="btn btn-primary mt-4">Soumettre ma candidature</button>
        </form>
    <?php
    ?>
</body>
</html>