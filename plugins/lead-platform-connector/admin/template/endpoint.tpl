import(crud.tpl, {"type":"lead_endpoint"})

/* Active checkbox: render `checked` attribute from saved state. Value is hardcoded 1 in HTML. */
[data-v-lead_endpoint] input[type="checkbox"][data-v-lead_endpoint-active_checkbox]|addNewAttribute = <?php
	$v = $this->lead_endpoint['active'] ?? 0;
	if (! empty($v) && $v !== '0') echo 'checked';
?>

/* API key: never echo the stored ciphertext as the input's value.
   Update the placeholder to hint that a key is already stored. */
[data-v-lead_endpoint] input[name="api_key"]|addNewAttribute = <?php
	$hasKey = ! empty($this->lead_endpoint['api_key_enc']);
	echo 'placeholder="' . htmlspecialchars($hasKey ? 'Leave blank to keep current key' : 'lp_...', ENT_QUOTES) . '"';
?>
