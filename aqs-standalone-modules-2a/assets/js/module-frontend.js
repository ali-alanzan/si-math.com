/**
 * Module Frontend JavaScript
 * عرض وحل الموديلات في الـ Frontend
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var currentModule = null;
    var currentQuestionIndex = 0;
    var userAnswers = {};
    var startTime = null;
    var timerInterval = null;
    
    // Start module
    $(document).on('click', '.aqs-start-module', function(e) {
        e.preventDefault();
        
        var moduleId = $(this).data('module-id');
        loadModule(moduleId);
    });
    
    // Close modal
    $('#aqs-close-modal').on('click', function() {
        if (confirm('هل أنت متأكد من الخروج؟ سيتم فقدان إجاباتك.')) {
            closeModal();
        }
    });
    
    // Close modal on background click
    $('#aqs-module-modal').on('click', function(e) {
        if (e.target.id === 'aqs-module-modal') {
            if (confirm('هل أنت متأكد من الخروج؟ سيتم فقدان إجاباتك.')) {
                closeModal();
            }
        }
    });
    
    // Load module
    function loadModule(moduleId) {
        $.ajax({
            url: aqsModuleFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aqs_get_module_details',
                nonce: aqsModuleFrontend.nonce,
                module_id: moduleId
            },
            success: function(response) {
                if (response.success) {
                    currentModule = response.data;
                    currentQuestionIndex = 0;
                    userAnswers = {};
                    startTime = Date.now();
                    
                    renderModule();
                    $('#aqs-module-modal').css('display', 'flex');
                } else {
                    alert(response.data.message || 'حدث خطأ في تحميل الموديل');
                }
            },
            error: function() {
                alert('حدث خطأ في الاتصال');
            }
        });
    }
    
    // Render module
    function renderModule() {
        if (!currentModule || !currentModule.questions) {
            return;
        }
        
        var html = '';
        
        // Header
        html += '<div style="text-align: center; margin-bottom: 30px;">';
        html += '<h2 style="font-size: 32px; color: #2271b1; margin-bottom: 10px;">' + currentModule.title + '</h2>';
        html += '<div style="display: flex; justify-content: center; gap: 20px; color: #666; font-size: 14px;">';
        html += '<span>📊 ' + currentModule.questions.length + ' سؤال</span>';
        if (currentModule.time_limit > 0) {
            html += '<span id="aqs-timer">⏱️ <span id="aqs-time-remaining">' + formatTime(currentModule.time_limit * 60) + '</span></span>';
        }
        html += '</div>';
        html += '</div>';
        
        // Progress bar
        html += '<div style="background: #e0e0e0; height: 8px; border-radius: 4px; margin-bottom: 30px; overflow: hidden;">';
        html += '<div id="aqs-progress-bar" style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: 0%; transition: width 0.3s;"></div>';
        html += '</div>';
        
        // Question container
        html += '<div id="aqs-question-container"></div>';
        
        // Navigation
        html += '<div style="display: flex; justify-content: space-between; margin-top: 30px;">';
        html += '<button id="aqs-prev-question" class="button button-secondary" style="display: none;">← السابق</button>';
        html += '<div></div>';
        html += '<button id="aqs-next-question" class="button button-primary">التالي →</button>';
        html += '</div>';
        
        $('#aqs-module-content').html(html);
        
        renderQuestion();
        
        // Start timer if needed
        if (currentModule.time_limit > 0) {
            startTimer(currentModule.time_limit * 60);
        }
    }
    
    // Render current question
    function renderQuestion() {
        var question = currentModule.questions[currentQuestionIndex];
        var progress = ((currentQuestionIndex + 1) / currentModule.questions.length) * 100;
        
        $('#aqs-progress-bar').css('width', progress + '%');
        
        var html = '';
        html += '<div class="aqs-question-display" style="background: #f9f9f9; border-radius: 12px; padding: 30px; min-height: 300px;">';
        
        // Question number and text
        html += '<div style="margin-bottom: 25px;">';
        html += '<span style="display: inline-block; background: #2271b1; color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-bottom: 15px;">';
        html += 'السؤال ' + (currentQuestionIndex + 1) + ' من ' + currentModule.questions.length;
        html += '</span>';
        html += '<h3 style="font-size: 22px; color: #333; margin: 15px 0; line-height: 1.6;">' + question.text + '</h3>';
        html += '</div>';
        
        // Answers
        html += '<div class="aqs-answers-list" style="margin: 25px 0;">';
        
        question.answers.forEach(function(answer, index) {
            var isSelected = userAnswers[currentQuestionIndex] === index;
            var selectedClass = isSelected ? 'aqs-answer-selected' : '';
            
            html += '<label class="aqs-answer-item ' + selectedClass + '" style="display: block; background: #fff; border: 2px solid ' + (isSelected ? '#2271b1' : '#ddd') + '; border-radius: 8px; padding: 18px 20px; margin-bottom: 12px; cursor: pointer; transition: all 0.3s; font-size: 16px;">';
            html += '<input type="radio" name="answer" value="' + index + '" ' + (isSelected ? 'checked' : '') + ' style="margin-left: 12px;">';
            html += '<span>' + answer + '</span>';
            html += '</label>';
        });
        
        html += '</div>';
        html += '</div>';
        
        $('#aqs-question-container').html(html);
        
        // Show/hide navigation buttons
        if (currentQuestionIndex === 0) {
            $('#aqs-prev-question').hide();
        } else {
            $('#aqs-prev-question').show();
        }
        
        if (currentQuestionIndex === currentModule.questions.length - 1) {
            $('#aqs-next-question').text('إنهاء الامتحان ✓').removeClass('button-primary').addClass('button-success');
        } else {
            $('#aqs-next-question').text('التالي →').removeClass('button-success').addClass('button-primary');
        }
        
        // Answer selection
        $(document).off('change', 'input[name="answer"]');
        $(document).on('change', 'input[name="answer"]', function() {
            userAnswers[currentQuestionIndex] = parseInt($(this).val());
            
            // Update visual state
            $('.aqs-answer-item').removeClass('aqs-answer-selected').css('border-color', '#ddd');
            $(this).closest('.aqs-answer-item').addClass('aqs-answer-selected').css('border-color', '#2271b1');
        });
        
        // Hover effect
        $(document).off('mouseenter mouseleave', '.aqs-answer-item');
        $(document).on('mouseenter', '.aqs-answer-item', function() {
            if (!$(this).hasClass('aqs-answer-selected')) {
                $(this).css('border-color', '#2271b1');
            }
        });
        $(document).on('mouseleave', '.aqs-answer-item', function() {
            if (!$(this).hasClass('aqs-answer-selected')) {
                $(this).css('border-color', '#ddd');
            }
        });
    }
    
    // Next question
    $(document).on('click', '#aqs-next-question', function() {
        if (userAnswers[currentQuestionIndex] === undefined) {
            alert('الرجاء اختيار إجابة');
            return;
        }
        
        if (currentQuestionIndex === currentModule.questions.length - 1) {
            // Submit
            submitModule();
        } else {
            currentQuestionIndex++;
            renderQuestion();
        }
    });
    
    // Previous question
    $(document).on('click', '#aqs-prev-question', function() {
        if (currentQuestionIndex > 0) {
            currentQuestionIndex--;
            renderQuestion();
        }
    });
    
    // Submit module
    function submitModule() {
        if (Object.keys(userAnswers).length < currentModule.questions.length) {
            if (!confirm('لم تجب على جميع الأسئلة. هل تريد الإرسال؟')) {
                return;
            }
        }
        
        $('#aqs-next-question').prop('disabled', true).text('⏳ جاري الإرسال...');
        
        if (timerInterval) {
            clearInterval(timerInterval);
        }
        
        $.ajax({
            url: aqsModuleFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aqs_save_module_attempt',
                nonce: aqsModuleFrontend.nonce,
                module_id: currentModule.id,
                answers: userAnswers,
                time_taken: Math.floor((Date.now() - startTime) / 1000)
            },
            success: function(response) {
                if (response.success) {
                    showResults(response.data);
                } else {
                    alert(response.data.message || 'حدث خطأ في حفظ المحاولة');
                    $('#aqs-next-question').prop('disabled', false).text('إنهاء الامتحان ✓');
                }
            },
            error: function() {
                alert('حدث خطأ في الاتصال');
                $('#aqs-next-question').prop('disabled', false).text('إنهاء الامتحان ✓');
            }
        });
    }
    
    // Show results
    function showResults(data) {
        var html = '';
        
        html += '<div style="text-align: center; padding: 40px 20px;">';
        
        // Icon
        var icon = data.percentage >= 50 ? '🎉' : '😔';
        html += '<div style="font-size: 80px; margin-bottom: 20px;">' + icon + '</div>';
        
        // Title
        var title = data.percentage >= 50 ? 'تهانينا! لقد نجحت' : 'للأسف، لم تحقق النجاح';
        html += '<h2 style="font-size: 32px; color: #333; margin-bottom: 15px;">' + title + '</h2>';
        
        // Score
        html += '<div style="font-size: 48px; font-weight: bold; color: #2271b1; margin: 30px 0;">';
        html += data.percentage.toFixed(1) + '%';
        html += '</div>';
        
        // Details
        html += '<div style="display: flex; justify-content: center; gap: 30px; margin: 30px 0; font-size: 18px; color: #666;">';
        html += '<div><strong style="color: #46b450;">' + data.correct + '</strong> إجابة صحيحة</div>';
        html += '<div><strong style="color: #dc3232;">' + (data.total - data.correct) + '</strong> إجابة خاطئة</div>';
        html += '</div>';
        
        // Message
        if (data.percentage >= 50) {
            html += '<p style="font-size: 16px; color: #666; margin-top: 20px;">أحسنت! لقد أظهرت مستوى جيداً</p>';
        } else {
            html += '<p style="font-size: 16px; color: #666; margin-top: 20px;">لا تقلق، يمكنك المحاولة مرة أخرى</p>';
        }
        
        // Close button
        html += '<button id="aqs-close-results" class="button button-primary button-large" style="margin-top: 40px; padding: 15px 40px; font-size: 18px;">';
        html += 'إغلاق';
        html += '</button>';
        
        html += '</div>';
        
        $('#aqs-module-content').html(html);
    }
    
    // Close results
    $(document).on('click', '#aqs-close-results', function() {
        closeModal();
        location.reload(); // Reload to show updated modules
    });
    
    // Start timer
    function startTimer(seconds) {
        var remaining = seconds;
        
        timerInterval = setInterval(function() {
            remaining--;
            
            $('#aqs-time-remaining').text(formatTime(remaining));
            
            // Warning at 5 minutes
            if (remaining === 300) {
                $('#aqs-timer').css('color', '#ff9800');
                alert('⚠️ باقي 5 دقائق فقط!');
            }
            
            // Warning at 1 minute
            if (remaining === 60) {
                $('#aqs-timer').css('color', '#f44336');
                alert('⚠️ باقي دقيقة واحدة!');
            }
            
            // Time's up
            if (remaining <= 0) {
                clearInterval(timerInterval);
                alert('⏰ انتهى الوقت!');
                submitModule();
            }
        }, 1000);
    }
    
    // Format time
    function formatTime(seconds) {
        var minutes = Math.floor(seconds / 60);
        var secs = seconds % 60;
        return minutes + ':' + (secs < 10 ? '0' : '') + secs;
    }
    
    // Close modal
    function closeModal() {
        $('#aqs-module-modal').hide();
        currentModule = null;
        currentQuestionIndex = 0;
        userAnswers = {};
        
        if (timerInterval) {
            clearInterval(timerInterval);
        }
    }
});
