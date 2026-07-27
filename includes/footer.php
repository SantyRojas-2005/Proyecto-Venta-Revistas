<?php
/**
 * includes/footer.php
 * Cierra el layout, imprime el pie y carga los scripts comunes.
 */
?>
  <footer class="pie">
    <span>Sistema de Envío de Revistas a Domicilio</span>
    <span>Proyecto académico — PHP nativo + PDO · <?= date('Y') ?></span>
  </footer>
</main><!-- /.contenido -->
</div><!-- /.layout -->

<script src="<?= $rutaBase ?>assets/js/main.js"></script>
<?php if (!empty($scriptsExtra)): ?>
  <?php foreach ($scriptsExtra as $script): ?>
    <script src="<?= $rutaBase . $script ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
