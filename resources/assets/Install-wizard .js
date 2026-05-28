export default class InstallWizard {
	#naja;
	#statusFile;
	#statusCounter;
	#installBtn;

	initialize(naja) {
		this.#naja = naja;
		this.#init();
		naja.snippetHandler.addEventListener('afterUpdate', () => this.#init());
	}

	#init() {
		this.#initSendButton();
		this.#initInstallButton();
	}

	#initSendButton() {
		const button = document.getElementById('btn-send');
		if (!button || button.dataset.initialized) return;

		button.dataset.initialized = 'true';
		button.addEventListener('click', (e) => {
			button.disabled = true;
			this.#naja
				.makeRequest('GET', e.target.dataset.url, null, { history: false })
				.catch(() => { button.disabled = false; });
		});
	}

	#initInstallButton() {
		const btn = document.getElementById('installBtn');
		if (!btn || btn.dataset.initialized) return;

		btn.dataset.initialized = 'true';
		btn.addEventListener('click', async () => {
			btn.disabled = true;
			await this.#runInstall();
		});
	}

	#applyStatus(state) {
		['running', 'success', 'error'].forEach(s => {
			const el = document.getElementById(`install-status-${s}`);
			if (!el) return;
			el.classList.toggle('d-none', s !== state);
			el.classList.toggle('d-flex', s === state);
		});
	}

	#updateChip(state, file = null, done = null, total = null) {
		this.#applyStatus(state);
		const counter = done !== null ? `${done} / ${total}` : null;

		if (state === 'running') {
			if (file && this.#statusFile) this.#statusFile.textContent = file;
			if (counter) document.getElementById('status-counter').textContent = counter;
		} else if (state === 'success') {
			if (counter) document.getElementById('status-counter-success').textContent = counter;
		} else if (state === 'error') {
			if (counter) document.getElementById('status-counter-error').textContent = counter;
		}
	}

	async #runInstall() {
		this.#installBtn = document.getElementById('installBtn');
		this.#statusFile = document.getElementById('status-file');
		this.#statusCounter = document.getElementById('status-counter');

		const steps = document.querySelectorAll('#steps .step');
		const total = steps.length;
		let done = 0;
		let allOk = true;

		for (const step of steps) {
			const { url, step: file } = step.dataset;

			this.#updateChip('running', file, done + 1, total);

			try {
				const res = await fetch(url).then(r => r.json());

				if (res.status === 'success') {
					done++;
				} else {
					allOk = false;
					break;
				}
			} catch {
				allOk = false;
				break;
			}
		}

		this.#finalize(allOk, done, total);
	}

	#finalize(allOk, done, total) {
		if (allOk) {
			this.#updateChip('success', null, total, total);
		} else {
			this.#updateChip('error', null, done, total);
			if (this.#installBtn) this.#installBtn.disabled = false;
		}

		const targetId = allOk ? 'steps-done' : 'steps-fail';
		const targetUrl = document.getElementById(targetId)?.dataset.doneUrl;

		if (targetUrl) {
			this.#naja.makeRequest('GET', targetUrl, null, { history: false });
		}
	}
}
