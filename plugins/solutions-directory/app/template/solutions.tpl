@solutions = [data-v-component-plugin-solutions-directory-solutions]

// |before, not |prepend: the PHP has to sit outside the element, because the
// rule below replaces the element's children with the component's HTML.
@solutions|before = <?php
	if (isset($_solutions_idx)) $_solutions_idx++; else $_solutions_idx = 0;
	$solutions = $this->_component['plugin_solutions_directory_solutions'][$_solutions_idx] ?? [];
?>

// Tells the plugin's stored-content renderer that this block is already done,
// so a block coming from a theme template is never rendered twice.
@solutions|data-sd-rendered = <?php echo '1'; ?>

// No modifier means innerHTML. vtpl has no `innerHTML` modifier - naming one
// writes an innerHTML="" attribute onto the element instead.
@solutions = <?php echo $solutions['html'] ?? ''; ?>
