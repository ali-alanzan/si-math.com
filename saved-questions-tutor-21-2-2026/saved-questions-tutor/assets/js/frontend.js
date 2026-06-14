/**
 * Frontend JavaScript for Saved Questions Tutor
 * Simple and direct approach
 *
 * @package Saved_Questions_Tutor
 */


setInterval(function () {
	let btn = jQuery(".sqt-save-btn:visible");
	
	let tutor_quiz_id = jQuery(`[name="tutor_quiz_id"]`).val(),
		tutor_question_id = jQuery(btn).attr("data-question-id");
    if( btn.hasClass("saved") ) {
        return;
    }
    
	jQuery.ajax({
		url: sqt_vars.ajax_url,
		type: 'POST',
		data: {
			action: 'sqt_get_saved_questions', // Matches the PHP hook
			security: sqt_vars.nonce,
			tutor_quiz_id,
			tutor_question_id,

		},
		dataType: "JSON",
		async: true
	})
	.done(function ajaxDone(response) {
        jQuery(".sqt-save-btn:visible").addClass("saved").text("Saved");
	})
	.fail(function ajaxFailed(e) {
	});
}, 4400);







(function($) {
	'use strict';

	const SQT = {
		init: function() {
			this.injectSaveButtons();
			this.bindEvents();
		},

		/**
		 * Inject save buttons into quiz questions - SIMPLE VERSION
		 */
		injectSaveButtons: function() {
			// Wait a bit for page to load
			setTimeout(() => {
				this.addButtonsToQuestions();
			}, 500);
		},

		/**
		 * Add buttons to questions - SIMPLE METHOD
		 */
		addButtonsToQuestions: function() {
			// Find all radio buttons and checkboxes (these are in questions)
			const answerInputs = document.querySelectorAll('input[type="radio"], input[type="checkbox"]');
			
			if (answerInputs.length === 0) {
				console.log('SQT: No answer inputs found');
				return;
			}

			// Group inputs by question (find their parent question container)
			const questionContainers = new Set();
			
			answerInputs.forEach(input => {
				// Find the question container - go up the DOM tree
				let container = input.closest('div');
				let levels = 0;
				
				// Go up to find question container (usually 3-5 levels up)
				while (container && levels < 10) {
					const text = container.textContent || '';
					// Check if this looks like a question container
					if (text.length > 50 && text.length < 2000) {
						// Check if it has question-like structure
						if (text.match(/\d+\./) || container.querySelector('h3, h4, h5, strong')) {
							// Skip if it's an answer option
							if (!container.closest('label') && !container.classList.contains('answer')) {
								questionContainers.add(container);
								break;
							}
						}
					}
					container = container.parentElement;
					levels++;
				}
			});

			// Add button to each question
			questionContainers.forEach(container => {
				// Skip if button already exists
				if (container.querySelector('.sqt-save-btn')) {
					return;
				}

				// Find where to insert button (after question text, before answers)
				const questionText = container.querySelector('h3, h4, h5, strong, p');
				const firstInput = container.querySelector('input[type="radio"], input[type="checkbox"]');
				
				let insertPoint = null;
				if (questionText) {
					insertPoint = questionText;
				} else if (firstInput) {
					insertPoint = firstInput.parentElement;
				} else {
					insertPoint = container.firstElementChild;
				}

				if (insertPoint) {
					// Create button
					const btn = this.createSimpleSaveButton(container);
					
					// Insert button
					const wrapper = document.createElement('div');
					wrapper.className = 'sqt-save-btn-wrapper';
					wrapper.style.cssText = 'margin: 10px 0; display: block;';
					wrapper.appendChild(btn);
					
					// Insert after question text or before first input
					if (insertPoint && insertPoint.parentNode) {
						insertPoint.parentNode.insertBefore(wrapper, insertPoint.nextSibling);
					} else if (container.firstElementChild) {
						container.insertBefore(wrapper, container.firstElementChild);
					} else {
						container.appendChild(wrapper);
					}
					
				}
			});

			console.log('SQT: Added buttons to', questionContainers.size, 'questions');
		},

		/**
		 * Create simple save button
		 */
		createSimpleSaveButton: function(questionContainer) {
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'sqt-save-btn';
			btn.innerHTML = `
				<span class="dashicons dashicons-bookmark"></span>
				<span class="sqt-btn-text">${sqtData.strings.save || 'Save Question'}</span>
			`;

			// Store question data
			const questionText = questionContainer.textContent || '';
			const questionId = questionContainer.getAttribute('data-question-id') || 
							   questionContainer.id || 
							   'q_' + Date.now();
			
			btn.setAttribute('data-question-container', 'true');
			btn.setAttribute('data-question-id', questionId);
			btn.setAttribute('data-question-text', questionText.substring(0, 500));

			return btn;
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Save button click
			$(document).on('click', '.sqt-save-btn', this.handleSaveClick.bind(this));

			// Remove button click
			$(document).on('click', '.sqt-remove-btn', this.handleRemoveClick.bind(this));
			$(document).on('click', '.sqt-remove-btn-dashboard', this.handleRemoveClick.bind(this));
		},

		/**
		 * Handle save button click
		 */
		handleSaveClick: function(e) {
			e.preventDefault();
			const btn = $(e.currentTarget);
			
			// Check if already saved
			if (btn.hasClass('saved')) {
				this.showMessage(sqtData.strings.saved || 'Already saved', 'info');
				return;
			}

			// Disable button
			btn.prop('disabled', true).addClass('saving');
			btn.find('.sqt-btn-text').text(sqtData.strings.saving || 'Saving...');

			// Get question container
			const questionContainer = btn.closest('[data-question-container]').parent().parent() || 
									  btn.closest('div').parent();
			
			// Extract question data
			const questionId = btn.data('question-id') || 'q_' + Date.now();
			const quizId = this.getQuizId();
			const questionText = btn.data('question-text') || questionContainer.text() || '';
			
			// Get question HTML
			const questionHTML = questionContainer.html() || questionContainer[0].innerHTML || '';
			
			// Extract choices
			const choices = [];
			questionContainer.find('input[type="radio"], input[type="checkbox"]').each(function() {
				const label = $(this).next('label').text() || 
							  $(this).closest('label').text() || 
							  $(this).val() || '';
				if (label.trim()) {
					choices.push(label.trim());
				}
			});

			// Prepare data
			const data = {
				quiz_id: quizId,
				question_id: questionId,
				question_content: questionHTML.substring(0, 2000), // Limit length
				meta: {
					choices: choices,
					type: questionContainer.find('input[type="checkbox"]').length > 0 ? 'multiple_choice' : 'single_choice'
				},
				source_url: window.location.href
			};

			// Make API request
			wp.apiFetch({
				path: 'saved-questions/v1/save',
				method: 'POST',
				data: data,
				headers: {
					'X-WP-Nonce': sqtData.nonce,
				},
			})
			.then(response => {
				btn.removeClass('saving').addClass('saved');
				btn.find('.sqt-btn-text').text(sqtData.strings.saved || 'Saved');
				this.showMessage(response.message || sqtData.strings.saved || 'Question saved!', 'success');
			})
			.catch(error => {
				btn.prop('disabled', false).removeClass('saving');
				btn.find('.sqt-btn-text').text(sqtData.strings.save || 'Save Question');
				const message = error.message || sqtData.strings.error || 'Error occurred';
				this.showMessage(message, 'error');
				console.error('SQT Save Error:', error);
			});
		},

		/**
		 * Get quiz ID
		 */
		getQuizId: function() {
			const quizId = 
				document.querySelector('[data-quiz-id]')?.getAttribute('data-quiz-id') ||
				window.location.pathname.match(/quiz[\/\-](\d+)/i)?.[1] ||
				$('body').data('quiz-id') ||
				null;

			return quizId || get_the_ID?.() || 0;
		},

		/**
		 * Handle remove button click
		 */
		handleRemoveClick: function(e) {
			e.preventDefault();
			const btn = $(e.currentTarget);
			const savedId = btn.data('saved-id');

			if (!confirm(sqtData.strings.confirmRemove || 'Remove this question?')) {
				return;
			}

			btn.prop('disabled', true).text(sqtData.strings.removing || 'Removing...');

			wp.apiFetch({
				path: 'saved-questions/v1/remove',
				method: 'DELETE',
				data: { saved_id: savedId },
				headers: {
					'X-WP-Nonce': sqtData.nonce,
				},
			})
			.then(response => {
				btn.closest('.sqt-question-item, .sqt-question-item-dashboard').fadeOut(300, function() {
					$(this).remove();
				});
				this.showMessage(response.message || 'Removed', 'success');
			})
			.catch(error => {
				btn.prop('disabled', false).text(sqtData.strings.remove || 'Remove');
				this.showMessage(error.message || sqtData.strings.error || 'Error', 'error');
			});
		},

		/**
		 * Show message/toast
		 */
		showMessage: function(message, type) {
			type = type || 'info';
			const toast = $('<div class="sqt-toast sqt-toast-' + type + '">' + message + '</div>');
			$('body').append(toast);
			
			setTimeout(() => {
				toast.addClass('show');
			}, 10);

			setTimeout(() => {
				toast.removeClass('show');
				setTimeout(() => toast.remove(), 300);
			}, 3000);
		},
	};

	// Initialize when DOM is ready
	$(document).ready(function() {
		SQT.init();
	});

	// Also try after page fully loads
	window.addEventListener('load', function() {
		setTimeout(() => {
			SQT.addButtonsToQuestions();
		}, 1000);
	});

	// Try again after 2 seconds (for dynamic content)
	setTimeout(() => {
		SQT.addButtonsToQuestions();
	}, 2000);

})(jQuery);
