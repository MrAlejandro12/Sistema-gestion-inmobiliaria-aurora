<?php
// ============================================================
// includes/kpi_card.php - Helper para tarjetas KPI
// Reduce duplicación entre los 4 dashboards (SonarQube S4144)
// Uso: kpiCard('Ingresos', '$42,000', '💵', 'blue', 'USD acumulado')
// ============================================================

/**
 * Renderiza una tarjeta KPI estándar del dashboard.
 *
 * @param string $label    Etiqueta de la métrica
 * @param string $value    Valor principal a mostrar
 * @param string $icon     Emoji o símbolo del ícono
 * @param string $color    Variante de color: blue|gold|green|orange
 * @param string $sub      Texto secundario opcional
 */
function kpiCard(string $label, string $value, string $icon, string $color = 'blue', string $sub = ''): void {
    $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeValue = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeIcon  = htmlspecialchars($icon,  ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeSub   = htmlspecialchars($sub,   ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Whitelist color to prevent XSS through class injection
    $allowedColors = ['blue', 'gold', 'green', 'orange', 'red', 'teal'];
    $safeColor = in_array($color, $allowedColors, true) ? $color : 'blue';
    echo <<<HTML
<div class="kpi-card {$safeColor}">
  <div class="kpi-icon">{$safeIcon}</div>
  <div class="kpi-label">{$safeLabel}</div>
  <div class="kpi-value">{$safeValue}</div>
  <?php echo $safeSub ? "<div class=\"kpi-sub\">{$safeSub}</div>" : ''; ?>
</div>
HTML;
}
