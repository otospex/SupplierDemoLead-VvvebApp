@leadform = [data-v-component-plugin-lead-platform-connector-leadform]

@leadform|prepend = <?php
	$vvveb_is_page_edit = Vvveb\isEditor();

	if (isset($_leadform_idx)) $_leadform_idx++; else $_leadform_idx = 0;
	$previous_component = isset($current_component) ? $current_component : null;
	$leadform = $current_component = $this->_component['plugin_lead_platform_connector_leadform'][$_leadform_idx] ?? [];

	$_lpc_endpoint    = htmlspecialchars($leadform['endpoint']    ?? '', ENT_QUOTES);
	$_lpc_csrf        = htmlspecialchars($leadform['csrf']        ?? '', ENT_QUOTES);
	$_lpc_submit_url  = htmlspecialchars($leadform['submit_url']  ?? '', ENT_QUOTES);
	$_lpc_honeypot    = htmlspecialchars($leadform['honeypot']    ?? 'company_website', ENT_QUOTES);
	$_lpc_success_url = htmlspecialchars($leadform['success_url'] ?? '', ENT_QUOTES);
	$_lpc_success_msg = htmlspecialchars($leadform['success_msg'] ?? '', ENT_QUOTES);
	$_lpc_error_msg   = htmlspecialchars($leadform['error_msg']   ?? '', ENT_QUOTES);
	$_lpc_render_ts   = (int) ($leadform['render_ts'] ?? 0);
?>

@leadform|data-endpoint    = <?php echo $_lpc_endpoint; ?>
@leadform|data-csrf        = <?php echo $_lpc_csrf; ?>
@leadform|data-submit-url  = <?php echo $_lpc_submit_url; ?>
@leadform|data-honeypot    = <?php echo $_lpc_honeypot; ?>
@leadform|data-success-url = <?php echo $_lpc_success_url; ?>
@leadform|data-success-msg = <?php echo $_lpc_success_msg; ?>
@leadform|data-error-msg   = <?php echo $_lpc_error_msg; ?>
@leadform|data-render-ts   = <?php echo $_lpc_render_ts; ?>

@leadform|after = <?php
	if (! isset($GLOBALS['_lpc_runtime_emitted'])) {
		$GLOBALS['_lpc_runtime_emitted'] = true;
		$src = (defined('PUBLIC_PATH') ? PUBLIC_PATH : '/') . 'plugins/lead-platform-connector/js/lead-form.js';
		echo '<script src="' . htmlspecialchars($src, ENT_QUOTES) . '" defer></script>';
	}
?>
