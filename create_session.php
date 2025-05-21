<?php 
// Tutaj byłaby logika sprawdzająca, czy admin jest zalogowany (auth.php)
// if (!isAdmin()) { header('Location: index.php'); exit; }
include 'includes/header.php'; 
?>

<h2>Tworzenie Nowej Sesji Rady Pedagogicznej</h2>
<!-- Lub "Edycja Sesji" jeśli jest parametr GET z ID sesji -->

<form action="admin_dashboard.php" method="POST"> <!-- Akcja do zmiany na skrypt PHP -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Podstawowe Informacje o Sesji</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label for="session_name">Nazwa sesji:</label>
                <input type="text" id="session_name" name="session_name" class="form-control" required value="Rada Pedagogiczna - <?php echo date('F Y'); ?>">
            </div>
            <div class="form-group">
                <label for="planned_date">Planowana data rozpoczęcia:</label>
                <input type="datetime-local" id="planned_date" name="planned_date" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Uchwały do Głosowania</h3>
        </div>
        <div class="card-body">
            <div id="resolutions-list">
                <!-- Pierwsza uchwała (wymagana) -->
                <div class="resolution-input-group card mb-3">
                    <h4>Uchwała #1</h4>
                    <div class="form-group">
                        <label for="resolution_name_1">Nazwa uchwały:</label>
                        <input type="text" name="resolutions[1][name]" id="resolution_name_1" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="resolution_description_1">Opis uchwały (opcjonalnie):</label>
                        <textarea name="resolutions[1][description]" id="resolution_description_1" class="form-control"></textarea>
                    </div>
                    <!-- Nie można usunąć pierwszej uchwały, jeśli jest jedyna -->
                     <button type="button" class="btn btn-danger btn-sm btn-remove-resolution" style="display:none;">Usuń tę uchwałę</button>
                </div>
            </div>
            <button type="button" id="add-resolution-btn" class="btn btn-secondary mt-2">Dodaj kolejną uchwałę</button>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-success">Utwórz Sesję</button>
        <a href="admin_dashboard.php" class="btn">Anuluj</a>
    </div>
</form>

<?php include 'includes/footer.php'; ?>