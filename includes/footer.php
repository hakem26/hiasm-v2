</div><!-- /container-xl -->
    </div><!-- /page-body -->

    <!-- ── Footer ─────────────────────────────────────────── -->
    <footer class="footer footer-transparent d-print-none">
      <div class="container-xl">
        <div class="row text-center align-items-center">
          <div class="col-12 col-lg-auto mt-3 mt-lg-0">
            <ul class="list-inline list-inline-dots mb-0">
              <li class="list-inline-item">
                <?= APP_NAME ?> v<?= APP_VERSION ?>
              </li>
              <li class="list-inline-item text-muted">
                <?= toJalali(date('Y-m-d')) ?>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </footer>

  </div><!-- /page-wrapper -->
</div><!-- /wrapper -->

<?php $vendor = BASE_URL . '/assets/vendor'; ?>

<!-- Tabler JS -->
<script src="<?= $vendor ?>/tabler/js/tabler.min.js"></script>
<!-- Tabulator -->
<script src="<?= $vendor ?>/tabulator/tabulator.min.js"></script>
<!-- ApexCharts -->
<script src="<?= $vendor ?>/apexcharts/apexcharts.min.js"></script>
<!-- JalaliDatePicker -->
<script src="<?= $vendor ?>/jalali-datepicker/JalaliDatePicker.min.js"></script>
<!-- JS سفارشی -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>

<?php if (!empty($extraJs)): ?>
  <?php foreach ($extraJs as $js): ?>
    <script src="<?= e($js) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($inlineJs)): ?>
  <script><?= $inlineJs ?></script>
<?php endif; ?>

</body>
</html>