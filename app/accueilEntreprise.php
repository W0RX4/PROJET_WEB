<?php
    session_start();
    if($_SESSION['type'] !== 'entreprise'){
        header('Location: ../connection/login.php');
        exit;
    }

    $username = $_SESSION['username'] ?? '';
    echo "Bienvenue sur la page d'accueil " . $username . " !";

    echo "<br><br>";
    echo "Ajouter un stage : ";
    ?>  
        <form action="ajouterStage.php" method="post">
            <label for="title">Titre du stage:</label>
            <input type="text" id="title" name="title" required><br><br>

            <label for="description">Description du stage:</label>
            <textarea id="description" name="description" required></textarea><br><br>

            <label for="location">Lieu du stage:</label>
            <input type="text" id="location" name="location" required><br><

            <label for="startDate">Date de début:</label>
            <input type="date" id="startDate" name="startDate" required><br><br>

            <label for="endDate">Date de fin:</label>
            <input type="date" id="endDate" name="endDate" required><br><br>

            <input type="submit" value="Ajouter le stage">
        </form>

    <?php
    if(isset($_SESSION['result'])){
        echo $_SESSION['result'];
        unset($_SESSION['result']);
    }
?>