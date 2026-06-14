/**
 * Advanced Quiz System - Enhanced JavaScript
 * Fixed Chat System + Standalone Quiz + Mistakes + Predictor
 */

(function($) {
    'use strict';

    // ===========================
    // CHAT SYSTEM - FIXED
    // ===========================
    
    const AQSChat = {
        interval: null,
        lastMessageId: 0,
        
        init: function() {
            if ($('#aqs-chat-widget').length === 0) return;
            
            this.bindEvents();
            this.loadMessages();
            this.loadOnlineUsers();
            
            // Poll for new messages every 3 seconds
            this.interval = setInterval(() => {
                this.loadMessages();
                this.loadOnlineUsers();
            }, 3000);
        },
        
        bindEvents: function() {
            // Send message on button click
            $('#aqs-chat-send-btn').on('click', () => this.sendMessage());
            
            // Send message on Enter (but allow Shift+Enter for new line)
            $('#aqs-chat-input').on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    AQSChat.sendMessage();
                }
            });
            
            // Mark messages as read when chat is opened
            $('.aqs-chat-header').on('click', function() {
                if (!$('#aqs-chat-widget').hasClass('aqs-chat-minimized')) {
                    AQSChat.markAsRead();
                }
            });
        },
        
        sendMessage: function() {
            const $input = $('#aqs-chat-input');
            const message = $input.val().trim();
            
            if (!message) return;
            
            const $sendBtn = $('#aqs-chat-send-btn');
            $sendBtn.prop('disabled', true);
            
            $.ajax({
                url: aqsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aqs_send_message',
                    nonce: aqsData.nonce,
                    message: message,
                    course_id: this.getCurrentCourseId()
                },
                success: function(response) {
                    if (response.success) {
                        $input.val('');
                        AQSChat.loadMessages();
                    } else {
                        alert(response.data.message || 'فشل إرسال الرسالة');
                    }
                },
                error: function() {
                    alert('حدث خطأ أثناء إرسال الرسالة');
                },
                complete: function() {
                    $sendBtn.prop('disabled', false);
                    $input.focus();
                }
            });
        },
        
        loadMessages: function() {
            $.ajax({
                url: aqsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aqs_get_messages',
                    nonce: aqsData.nonce,
                    course_id: this.getCurrentCourseId(),
                    last_id: this.lastMessageId
                },
                success: function(response) {
                    if (response.success && response.data.messages) {
                        AQSChat.renderMessages(response.data.messages);
                        
                        // Update last message ID
                        if (response.data.messages.length > 0) {
                            AQSChat.lastMessageId = Math.max(
                                ...response.data.messages.map(m => parseInt(m.id))
                            );
                        }
                    }
                }
            });
        },
        
        renderMessages: function(messages) {
            const $container = $('#aqs-chat-messages');
            const currentUserId = aqsData.userId;
            
            if (messages.length === 0 && $container.children().length === 0) {
                $container.html('<p class="aqs-chat-empty">' + aqsData.strings.no_messages + '</p>');
                return;
            }
            
            // Remove empty message if exists
            $('.aqs-chat-empty').remove();
            
            messages.forEach(function(msg) {
                // Check if message already exists
                if ($container.find('[data-message-id="' + msg.id + '"]').length > 0) {
                    return;
                }
                
                const isOwn = parseInt(msg.sender_id) === parseInt(currentUserId);
                const timeAgo = AQSChat.timeAgo(msg.created_at);
                
                const $message = $('<div>', {
                    'class': 'aqs-chat-message' + (isOwn ? ' own' : ''),
                    'data-message-id': msg.id
                });
                
                const $avatar = $('<img>', {
                    'class': 'aqs-chat-message-avatar',
                    'src': msg.avatar,
                    'alt': msg.sender_name
                });
                
                const $content = $('<div>', {'class': 'aqs-chat-message-content'});
                
                if (!isOwn) {
                    $content.append($('<div>', {
                        'class': 'aqs-chat-message-name',
                        'text': msg.sender_name
                    }));
                }
                
                $content.append($('<div>', {
                    'class': 'aqs-chat-message-text',
                    'text': msg.message
                }));
                
                $content.append($('<div>', {
                    'class': 'aqs-chat-message-time',
                    'text': timeAgo
                }));
                
                $message.append($avatar).append($content);
                $container.append($message);
            });
            
            // Scroll to bottom
            $container.scrollTop($container[0].scrollHeight);
        },
        
        loadOnlineUsers: function() {
            $.ajax({
                url: aqsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aqs_get_online_users',
                    nonce: aqsData.nonce,
                    course_id: this.getCurrentCourseId()
                },
                success: function(response) {
                    if (response.success && response.data.users) {
                        AQSChat.renderOnlineUsers(response.data.users);
                    }
                }
            });
        },
        
        renderOnlineUsers: function(users) {
            const $container = $('#aqs-chat-online-users');
            const $count = $('#aqs-online-count');
            
            $container.empty();
            $count.text(users.length + ' متصل');
            
            users.forEach(function(user) {
                const $user = $('<div>', {'class': 'aqs-online-user'});
                $user.append($('<span>', {'class': 'aqs-online-indicator'}));
                $user.append($('<span>', {'text': user.name}));
                $container.append($user);
            });
        },
        
        markAsRead: function() {
            $.ajax({
                url: aqsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aqs_mark_messages_read',
                    nonce: aqsData.nonce,
                    course_id: this.getCurrentCourseId()
                }
            });
        },
        
        getCurrentCourseId: function() {
            // Try to get course ID from page
            const courseId = $('body').data('course-id') || 
                           $('.tutor-course-details-wrapper').data('course-id') || 
                           0;
            return courseId;
        },
        
        timeAgo: function(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) return 'الآن';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' د';
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' س';
            return Math.floor(seconds / 86400) + ' ي';
        }
    };
    
    // ===========================
    // STANDALONE QUIZ SYSTEM
    // ===========================
    
    const AQSQuiz = {
        currentQuestion: 0,
        answers: {},
        startTime: null,
        timer: null,
        timeLimit: 0,
        
        init: function() {
            if ($('.aqs-standalone-quiz').length === 0) return;
            
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Start quiz
            $('.aqs-quiz-start-btn').on('click', () => this.startQuiz());
            
            // Navigation
            $('.aqs-quiz-nav-btn').on('click', function() {
                const index = $(this).data('index');
                AQSQuiz.showQuestion(index);
            });
            
            // Previous/Next buttons
            $('.aqs-quiz-btn-prev').on('click', () => this.previousQuestion());
            $('.aqs-quiz-btn-next').on('click', () => this.nextQuestion());
            
            // Finish quiz
            $('.aqs-quiz-btn-finish').on('click', () => this.finishQuiz());
            
            // Answer selection
            $(document).on('click', '.aqs-question-option', function() {
                const $question = $(this).closest('.aqs-quiz-question');
                const questionIndex = $question.data('index');
                const optionIndex = $(this).data('option');
                
                // Remove previous selection
                $question.find('.aqs-question-option').removeClass('selected');
                $(this).addClass('selected');
                
                // Save answer
                AQSQuiz.answers[questionIndex] = optionIndex;
                
                // Update navigator
                $('.aqs-quiz-nav-btn[data-index="' + questionIndex + '"]').addClass('answered');
                
                // Update progress
                AQSQuiz.updateProgress();
            });
        },
        
        startQuiz: function() {
            const quizId = $('.aqs-standalone-quiz').data('quiz-id');
            this.timeLimit = parseInt($('.aqs-standalone-quiz').data('time-limit')) || 60;
            
            $.ajax({
                url: aqsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aqs_start_quiz',
                    nonce: aqsData.nonce,
                    quiz_id: quizId
                },
                success: function(response) {
                    if (response.success) {
                        $('.aqs-quiz-intro').hide();
                        $('.aqs-quiz-interface').addClass('active');
                        
                        AQSQuiz.startTime = Date.now();
                        AQSQuiz.startTimer();
                        AQSQuiz.showQuestion(0);
                    }
                }
            });
        },
        
        startTimer: function() {
            const updateTimer = () => {
                const elapsed = Math.floor((Date.now() - this.startTime) / 1000);
                const remaining = (this.timeLimit * 60) - elapsed;
                
                if (remaining <= 0) {
                    this.finishQuiz(true); // Auto-submit
                    return;
                }
                
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                
                const timeText = minutes.toString().padStart(2, '0') + ':' + 
                               seconds.toString().padStart(2, '0');
                
                $('.aqs-quiz-timer-time').text(timeText);
                
                // Warning when less than 5 minutes
                if (remaining < 300) {
                    $('.aqs-quiz-timer').addClass('warning');
                }
            };
            
            updateTimer();
            this.timer = setInterval(updateTimer, 1000);
        },
        
        showQuestion: function(index) {
            this.currentQuestion = index;
            
            // Hide all questions
            $('.aqs-quiz-question').removeClass('active');
            
            // Show current question
            $('.aqs-quiz-question[data-index="' + index + '"]').addClass('active');
            
            // Update navigator
            $('.aqs-quiz-nav-btn').removeClass('current');
            $('.aqs-quiz-nav-btn[data-index="' + index + '"]').addClass('current');
            
            // Update buttons
            $('.aqs-quiz-btn-prev').toggle(index > 0);
            
            const totalQuestions = $('.aqs-quiz-question').length;
            const isLast = index === totalQuestions - 1;
            
            $('.aqs-quiz-btn-next').toggle(!isLast);
            $('.aqs-quiz-btn-finish').toggle(isLast);
            
            // Scroll to top
            $('.aqs-quiz-questions-container').scrollTop(0);
        },
        
        previousQuestion: function() {
            if (this.currentQuestion > 0) {
                this.showQuestion(this.currentQuestion - 1);
            }
        },
        
        nextQuestion: function() {
            const totalQuestions = $('.aqs-quiz-question').length;
            if (this.currentQuestion < totalQuestions - 1) {
                this.showQuestion(this.currentQuestion + 1);
            }
        },
        
        updateProgress: function() {
            const totalQuestions = $('.aqs-quiz-question').length;
            const answered = Object.keys(this.answers).length;
            const percentage = (answered / totalQuestions) * 100;
            
            $('.aqs-quiz-progress-fill').css('width', percentage + '%');
            $('.aqs-quiz-progress-text').text(
                'تم الإجابة على ' + answered + ' من ' + totalQuestions + ' سؤال'
            );
        },
        
        finishQuiz: function(autoSubmit = false) {
            if (!autoSubmit) {
                const totalQuestions = $('.aqs-quiz-question').length;
                const answered = Object.keys(this.answers).length;
                
                if (answered < totalQuestions) {
                    if (!confirm('لم تجب على جميع الأسئلة. هل تريد إنهاء الاختبار؟')) {
                        return;
                    }
                }
            }
            
            clearInterval(this.timer);
            
            const quizId = $('.aqs-standalone-quiz').data('quiz-id');
            const timeTaken = Math.floor((Date.now() - this.startTime) / 1000);
            
            $.ajax({
                url: aqsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aqs_finish_quiz',
                    nonce: aqsData.nonce,
                    quiz_id: quizId,
                    answers: JSON.stringify(this.answers),
                    time_taken: timeTaken
                },
                success: function(response) {
                    if (response.success) {
                        AQSQuiz.showResults(response.data);
                    }
                }
            });
        },
        
        showResults: function(data) {
            $('.aqs-quiz-interface').removeClass('active');
            
            const $results = $('.aqs-quiz-results');
            
            // Update score
            $('.aqs-results-score').text(data.score + '%');
            
            // Update icon based on pass/fail
            const icon = data.passed ? '🎉' : '😔';
            $('.aqs-results-icon').text(icon);
            
            // Update title
            const title = data.passed ? 'نجحت!' : 'للأسف لم تنجح';
            $('.aqs-results-title').text(title);
            
            // Update stats
            $('.aqs-results-stat-value').eq(0).text(data.correct);
            $('.aqs-results-stat-value').eq(1).text(data.wrong);
            $('.aqs-results-stat-value').eq(2).text(data.time_taken);
            
            $results.addClass('active');
        }
    };
    
    // ===========================
    // MISTAKES TRACKER
    // ===========================
    
    const AQSMistakes = {
        init: function() {
            if ($('.aqs-mistakes-container').length === 0) return;
            
            this.bindEvents();
            this.loadMistakes();
        },
        
        bindEvents: function() {
            $('.aqs-mistakes-filter').on('click', function() {
                $('.aqs-mistakes-filter').removeClass('active');
                $(this).addClass('active');
                
                const filter = $(this).data('filter');
                AQSMistakes.filterMistakes(filter);
            });
            
            $('#aqs-clear-mistakes').on('click', () => this.clearMistakes());
        },
        
        loadMistakes: function(courseId = 0) {
            $.ajax({
                url: aqsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aqs_get_mistakes',
                    nonce: aqsData.nonce,
                    course_id: courseId
                },
                success: function(response) {
                    if (response.success) {
                        AQSMistakes.renderMistakes(response.data.mistakes);
                    }
                }
            });
        },
        
        renderMistakes: function(mistakes) {
            const $container = $('.aqs-mistakes-list');
            $container.empty();
            
            if (mistakes.length === 0) {
                $container.html(`
                    <div class="aqs-mistakes-empty">
                        <div class="aqs-mistakes-empty-icon">🎉</div>
                        <div class="aqs-mistakes-empty-text">رائع! لا توجد أخطاء</div>
                    </div>
                `);
                return;
            }
            
            mistakes.forEach(function(mistake) {
                const $card = AQSMistakes.createMistakeCard(mistake);
                $container.append($card);
            });
        },
        
        createMistakeCard: function(mistake) {
            return $(`
                <div class="aqs-mistake-card">
                    <div class="aqs-mistake-question">${mistake.question_title}</div>
                    <div class="aqs-mistake-answers">
                        <div class="aqs-mistake-answer-row user-answer">
                            <div class="aqs-mistake-answer-label">إجابتك</div>
                            <div class="aqs-mistake-answer-text">${mistake.user_answer || 'لم تجب'}</div>
                        </div>
                        <div class="aqs-mistake-answer-row correct-answer">
                            <div class="aqs-mistake-answer-label">الإجابة الصحيحة</div>
                            <div class="aqs-mistake-answer-text">${mistake.correct_answer}</div>
                        </div>
                    </div>
                    ${mistake.explanation ? `
                        <div class="aqs-mistake-explanation">
                            <div class="aqs-mistake-explanation-title">التفسير</div>
                            <div>${mistake.explanation}</div>
                        </div>
                    ` : ''}
                    <div class="aqs-mistake-meta">
                        <span>📚 ${mistake.quiz_title || 'امتحان'}</span>
                        <span>📅 ${AQSMistakes.formatDate(mistake.created_at)}</span>
                    </div>
                </div>
            `);
        },
        
        filterMistakes: function(filter) {
            // Implement filtering logic
        },
        
        clearMistakes: function() {
            if (!confirm('هل تريد حذف جميع الأخطاء؟')) return;
            
            $.ajax({
                url: aqsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aqs_clear_mistakes',
                    nonce: aqsData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        AQSMistakes.loadMistakes();
                    }
                }
            });
        },
        
        formatDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('ar-EG');
        }
    };
    
    // ===========================
    // SCORE PREDICTOR
    // ===========================
    
    const AQSPredictor = {
        chart: null,
        
        init: function() {
            if ($('.aqs-predictor-container').length === 0) return;
            
            this.loadPrediction();
        },
        
        loadPrediction: function(courseId = 0) {
            $.ajax({
                url: aqsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aqs_predict_score',
                    nonce: aqsData.nonce,
                    course_id: courseId
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'success') {
                            AQSPredictor.renderPrediction(response.data);
                        } else {
                            AQSPredictor.showInsufficientData(response.data);
                        }
                    }
                }
            });
        },
        
        renderPrediction: function(data) {
            // Update prediction score
            $('.aqs-prediction-value').text(Math.round(data.prediction));
            $('.aqs-prediction-confidence').text(
                'مستوى الثقة: ' + data.confidence + '%'
            );
            
            // Update trend
            const trendClass = data.trend === 'improving' ? 'improving' : 
                              data.trend === 'declining' ? 'declining' : 'stable';
            $('.aqs-trend-indicator').attr('class', 'aqs-trend-indicator ' + trendClass);
            
            const trendText = data.trend === 'improving' ? 'أداء متحسن' :
                            data.trend === 'declining' ? 'أداء متراجع' : 'أداء مستقر';
            $('.aqs-trend-indicator').text(trendText);
            
            // Update statistics
            $('.aqs-stat-value').eq(0).text(data.statistics.average + '%');
            $('.aqs-stat-value').eq(1).text(data.statistics.max + '%');
            $('.aqs-stat-value').eq(2).text(data.statistics.min + '%');
            $('.aqs-stat-value').eq(3).text(data.statistics.attempts_count);
            
            // Update recommendations
            const $recommendations = $('.aqs-recommendations');
            $recommendations.find('.aqs-recommendation-item').remove();
            
            data.recommendation.forEach(function(rec) {
                $recommendations.append(
                    $('<div>', {
                        'class': 'aqs-recommendation-item',
                        'html': rec
                    })
                );
            });
            
            // Draw chart
            this.drawChart(data.history);
        },
        
        showInsufficientData: function(data) {
            $('.aqs-predictor-main').html(`
                <div class="aqs-predictor-card" style="grid-column: 1 / -1; text-align: center; padding: 60px;">
                    <div style="font-size: 64px; margin-bottom: 20px;">📊</div>
                    <h3>${data.message}</h3>
                    <p>قم بحل المزيد من الامتحانات للحصول على توقعات دقيقة</p>
                </div>
            `);
        },
        
        drawChart: function(history) {
            // Chart implementation using Chart.js
            // This would create a line chart showing score progression
        }
    };
    
    // ===========================
    // INITIALIZE ON DOCUMENT READY
    // ===========================
    
    $(document).ready(function() {
        AQSChat.init();
        AQSQuiz.init();
        AQSMistakes.init();
        AQSPredictor.init();
    });
    
    // Toggle chat widget
    window.aqsToggleChat = function() {
        $('#aqs-chat-widget').toggleClass('aqs-chat-minimized');
    };
    
})(jQuery);
