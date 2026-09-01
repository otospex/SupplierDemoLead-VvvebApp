@solutions = [data-v-component-plugin-solutions-directory-solutions]

@solutions|prepend = <?php
	if (isset($_solutions_idx)) $_solutions_idx++; else $_solutions_idx = 0;
	$solutions = $this->_component['plugin_solutions_directory_solutions'][$_solutions_idx] ?? [];
?>

@solutions|innerHTML = <?php echo $solutions['html'] ?? ''; ?>
