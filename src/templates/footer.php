<?php require_once __DIR__ . '/../config.php'; ?>
</main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Sistema de Achados e Perdidos - Versão <?php echo defined('APP_VERSION') ? APP_VERSION : 'N/A'; ?></p>
    </footer>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- JsBarcode Library -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <!-- Link to custom script.js -->
    <script src="/script.js"></script>
</body>
</html>
