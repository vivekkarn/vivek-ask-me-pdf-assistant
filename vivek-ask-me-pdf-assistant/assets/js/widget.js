(function () {
	'use strict';

	if (!window.AskMeAIConfig || document.querySelector('.ask-me-ai')) {
		return;
	}

	const config = window.AskMeAIConfig.config || {};
	const root = document.querySelector('[data-ask-me-ai-widget]');
	if (!root || !config.enabled) {
		return;
	}

	const state = {
		open: false,
		loading: false,
		sessionId: getSessionId(),
		messages: [],
	};

	function getSessionId() {
		const key = 'ask_me_ai_session';
		let value = window.localStorage.getItem(key);
		if (!value) {
			value = 'amai_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
			window.localStorage.setItem(key, value);
		}
		return value;
	}

	function escapeHtml(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function render() {
		const position = config.position === 'bottom-left' ? 'ask-me-ai--left' : 'ask-me-ai--right';
		root.innerHTML = `
			<div class="ask-me-ai ${position} ${state.open ? 'ask-me-ai--open' : ''}" style="--ask-me-ai-color: ${escapeHtml(config.widgetColor || '#1f7aec')}">
				<button class="ask-me-ai__launcher" type="button" aria-label="${escapeHtml(config.assistantName || 'Ask Me AI')}">
					<span class="ask-me-ai__launcher-icon" aria-hidden="true"></span>
				</button>
				<section class="ask-me-ai__panel" aria-live="polite" aria-label="${escapeHtml(config.assistantName || 'Ask Me AI')}">
					<header class="ask-me-ai__header">
						<div class="ask-me-ai__identity">
							<span class="ask-me-ai__avatar" aria-hidden="true">?</span>
							<div>
								<strong>${escapeHtml(config.assistantName || 'Ask Me AI')}</strong>
								<span>Usually replies instantly</span>
							</div>
						</div>
						<button class="ask-me-ai__close" type="button" aria-label="Close">&times;</button>
					</header>
					<div class="ask-me-ai__body">
						${renderMessages()}
						${state.loading ? '<div class="ask-me-ai__typing"><i></i><i></i><i></i></div>' : ''}
					</div>
					${renderSuggestions()}
					<form class="ask-me-ai__composer">
						<input class="ask-me-ai__input" name="message" autocomplete="off" placeholder="${escapeHtml(config.placeholder || 'Ask Me AI...')}" ${state.loading ? 'disabled' : ''} />
						<button class="ask-me-ai__send" type="submit" ${state.loading ? 'disabled' : ''} aria-label="Send">&rarr;</button>
					</form>
				</section>
			</div>
		`;

		bindEvents();
		scrollToBottom();
	}

	function renderMessages() {
		const messages = state.messages.length
			? state.messages
			: [{ role: 'assistant', content: config.welcomeMessage || 'Hi, how can I help?' }];

		return messages.map((message) => {
			const sources = message.sources && message.sources.length ? renderSources(message.sources) : '';
			return `
				<div class="ask-me-ai__message ask-me-ai__message--${message.role}">
					<div class="ask-me-ai__bubble">${formatMessage(message.content)}${sources}</div>
				</div>
			`;
		}).join('');
	}

	function renderSources(sources) {
		return `
			<div class="ask-me-ai__sources">
				${sources.slice(0, 3).map((source) => `
					<details>
						<summary>${escapeHtml(source.filename)}${source.page ? ' - p. ' + escapeHtml(source.page) : ''}</summary>
						<p>${escapeHtml(source.snippet || '')}</p>
					</details>
				`).join('')}
			</div>
		`;
	}

	function renderSuggestions() {
		if (state.messages.length || !config.suggestedQuestions || !config.suggestedQuestions.length) {
			return '';
		}

		return `
			<div class="ask-me-ai__suggestions">
				${config.suggestedQuestions.map((question) => `<button type="button" data-question="${escapeHtml(question)}">${escapeHtml(question)}</button>`).join('')}
			</div>
		`;
	}

	function formatMessage(content) {
		return escapeHtml(content || '').replace(/\n/g, '<br>');
	}

	function bindEvents() {
		const wrapper = root.querySelector('.ask-me-ai');
		const launcher = root.querySelector('.ask-me-ai__launcher');
		const close = root.querySelector('.ask-me-ai__close');
		const form = root.querySelector('.ask-me-ai__composer');

		launcher.addEventListener('click', () => {
			state.open = true;
			render();
			setTimeout(() => root.querySelector('.ask-me-ai__input')?.focus(), 80);
		});

		close.addEventListener('click', () => {
			state.open = false;
			render();
		});

		form.addEventListener('submit', (event) => {
			event.preventDefault();
			const input = form.querySelector('.ask-me-ai__input');
			submitQuestion(input.value);
		});

		wrapper.querySelectorAll('[data-question]').forEach((button) => {
			button.addEventListener('click', () => submitQuestion(button.dataset.question));
		});
	}

	function scrollToBottom() {
		const body = root.querySelector('.ask-me-ai__body');
		if (body) {
			body.scrollTop = body.scrollHeight;
		}
	}

	async function submitQuestion(question) {
		question = String(question || '').trim();
		if (!question || state.loading) {
			return;
		}

		state.messages.push({ role: 'user', content: question });
		state.loading = true;
		render();

		try {
			const response = await window.fetch(window.AskMeAIConfig.restUrl + '/chat', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.AskMeAIConfig.nonce,
				},
				body: JSON.stringify({
					message: question,
					session_id: state.sessionId,
				}),
			});

			const data = await response.json();
			if (!response.ok) {
				throw new Error(data.message || 'The assistant is unavailable right now.');
			}

			state.messages.push({
				role: 'assistant',
				content: data.answer,
				sources: data.sources || [],
			});
		} catch (error) {
			state.messages.push({
				role: 'assistant',
				content: error.message || 'The assistant is unavailable right now.',
			});
		} finally {
			state.loading = false;
			render();
		}
	}

	render();
})();
