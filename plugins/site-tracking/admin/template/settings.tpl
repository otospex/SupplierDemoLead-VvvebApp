import(crud.tpl, {"type":"tracking"})

[data-v-tracking] input[data-v-tracking-*]|value = <?php echo htmlspecialchars((string) ($this->tracking['@@__data-v-tracking-(*)__@@'] ?? ''), ENT_QUOTES); ?>
[data-v-tracking] textarea[data-v-tracking-*] = <?php echo htmlspecialchars((string) ($this->tracking['@@__data-v-tracking-(*)__@@'] ?? ''), ENT_QUOTES); ?>
