<?php
    require_once '../includes/header.php';
    if($_SESSION['type'] !== 'entreprise'){
        header('Location: ../connection/login.php');
        exit;
    }
?>

<div class="card">
    <h2>Bienvenue sur votre espace Entreprise, <?php echo htmlspecialchars($username); ?> !</h2>
    <p>Gérez vos offres de stage et suivez les candidatures.</p>
</div>

<?php if(isset($_SESSION['result'])): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($_SESSION['result']); ?>
    </div>
    <?php unset($_SESSION['result']); ?>
<?php endif; ?>

<div class="card">
    <h3>Ajouter un stage</h3>
    <form action="ajouterStage.php" method="post">
        <div class="form-group">
            <label class="form-label" for="title">Titre du stage</label>
            <input class="form-control" type="text" id="title" name="title" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="description">Description du stage</label>
            <textarea class="form-control" id="description" name="description" required></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="location">Lieu du stage</label>
            <input class="form-control" type="text" id="location" name="location" required>
        </div>

        <!-- The backend expects 'company' as an input but it wasn't here, I'll pass the logged company name invisibly or fix it -->
        <input type="hidden" name="company" value="<?php echo htmlspecialchars($username); ?>">

        <div class="grid-container">
            <div class="form-group">
                <label class="form-label" for="startDate">Date de début</label>
                <input class="form-control" type="date" id="startDate" name="startDate" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="endDate">Date de fin</label>
                <input class="form-control" type="date" id="endDate" name="endDate" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-4">Ajouter l'offre de stage</button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>