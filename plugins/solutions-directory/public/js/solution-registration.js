(function () {
	'use strict';

	const ENDPOINT = 'solution-registration';
	const SUCCESS = 'Merci. Votre solution sera examinée avant publication.';

	function isRegistration(form) {
		return form && form.getAttribute('data-v-endpoint') === ENDPOINT;
	}

	document.addEventListener('submit', function (event) {
		const form = event.target;
		if (!isRegistration(form)) return;
		const categories = form.querySelectorAll('input[name="categories[]"]:checked');
		const first = form.querySelector('input[name="categories[]"]');
		if (first) first.setCustomValidity(categories.length ? '' : 'Choisissez au moins une catégorie.');
		if (!categories.length) {
			event.preventDefault();
			event.stopImmediatePropagation();
			if (first) first.reportValidity();
		}
	}, true);

	document.addEventListener('change', function (event) {
		if (event.target && event.target.matches('input[name="categories[]"]')) {
			event.target.form.querySelector('input[name="categories[]"]').setCustomValidity('');
		}
	});

	document.addEventListener('lead-platform-connector:fields', function (event) {
		const form = event.detail && event.detail.form;
		if (!isRegistration(form)) return;
		const data = new FormData(form);
		event.detail.fields['categories[]'] = data.getAll('categories[]');
		event.detail.fields['alternative_to[]'] = data.getAll('alternative_to[]');
	});

	document.addEventListener('lead-platform-connector:success', function (event) {
		if (isRegistration(event.detail && event.detail.form)) {
			event.detail.message = SUCCESS;
		}
	});
})();
