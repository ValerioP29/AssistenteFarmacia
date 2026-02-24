document.addEventListener('appLoaded', () => {
	// when page is loaded
	fetchAppExample('val1', 'val2');
});

// Example for API request

function fetchAppExample(param1, param2) {
	const body = {
		key1: param1,
		otherKey: param2,
	};

	appFetchWithToken(AppURLs.api.sendTo(), {
		// change endpoint method
		method: 'POST', // POST, GET
		headers: {'Content-Type': 'application/json'},
		body: JSON.stringify(body),
	})
		.then((data) => {
			if (data.status) {
				document.dispatchEvent(new CustomEvent('appExampleSuccess', {detail: data?.data}));
				return;
			}
			document.dispatchEvent(new CustomEvent('appExampleError', {detail: {error: data.error}}));
		})
		.catch((err) => {
			document.dispatchEvent(new CustomEvent('appExampleError', {detail: {error: err}}));
		});
}

document.addEventListener('appExampleSuccess', function (event) {
	const data = event.detail;
	// do anything
});

document.addEventListener('appExampleError', function (event) {
	const error = event.detail;
	// do anything
});
