/**
 * Module Admin JavaScript
 * إدارة الموديلات من الـ Admin Panel
 */

jQuery(document).ready(function($) {
    'use strict';
    
    var questionIndex = $('.aqs-question-item').length;
    
    // Add new question
    $('#aqs-add-question').on('click', function() {
		console.log(1);
        var template = $('#aqs-question-template').html();
        template = template.replace(/{INDEX}/g, questionIndex);
        console.log(2);
        $('#aqs-questions-container').append(template);
        console.log(template);
        console.log(3);
        // Update question numbers
        updateQuestionNumbers();
        console.log(4);
        questionIndex++;
		console.log(questionIndex);
    });
    
    // Remove question
    $(document).on('click', '.aqs-remove-question', function() {
        if (confirm(aqsModuleData.strings.confirmDelete)) {
            $(this).closest('.aqs-question-item').remove();
            updateQuestionNumbers();
        }
    });
    
    // Add answer
    $(document).on('click', '.aqs-add-answer', function() {
        var $container = $(this).siblings('.aqs-answers-container');
        var questionIndex = $(this).closest('.aqs-question-item').data('index');
        var answerIndex = $container.find('.aqs-answer-option').length;
        
        var answerHtml = `
            <div class="aqs-answer-option">
                <input type="radio" 
                       name="questions[${questionIndex}][correct_answer]" 
                       value="${answerIndex}"
                       required>
                <input type="text" 
                       name="questions[${questionIndex}][answers][${answerIndex}]" 
                       placeholder="اكتب الإجابة..."
                       required>
                <button type="button" class="button aqs-remove-answer">❌</button>
            </div>
        `;
        
        $container.append(answerHtml);
    });
    
    // Remove answer
    $(document).on('click', '.aqs-remove-answer', function() {
        var $container = $(this).closest('.aqs-answers-container');
        
        if ($container.find('.aqs-answer-option').length > 1) {
            $(this).closest('.aqs-answer-option').remove();
        } else {
            alert('يجب أن يكون هناك إجابة واحدة على الأقل');
        }
    });
    
    // Update question numbers
    function updateQuestionNumbers() {
        $('.aqs-question-item').each(function(index) {
            $(this).find('.question-number').text(index + 1);
        });
    }
    
    // Form submission
    $('#aqs-module-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.html();
        
        // Validate
        if (!validateForm()) {
            return false;
        }
        
        // Disable button
        $submitBtn.prop('disabled', true).html('⏳ جاري الحفظ...');
        
        // Prepare data
        var formData = new FormData(this);
        formData.append('action', 'aqs_create_module');
        formData.append('nonce', aqsModuleData.nonce);
        
        $.ajax({
            url: aqsModuleData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    }
                } else {
                    alert(response.data.message || aqsModuleData.strings.error);
                    $submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                alert(aqsModuleData.strings.error);
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
        
        return false;
    });
    
    // Validate form
    function validateForm() {
        var title = $('#module_title').val().trim();
        var courseId = $('#course_id').val();
        var questions = $('.aqs-question-item').length;
        
        if (!title) {
            alert('الرجاء إدخال عنوان الموديل');
            $('#module_title').focus();
            return false;
        }
        
        if (!courseId) {
            alert('الرجاء اختيار الكورس');
            $('#course_id').focus();
            return false;
        }
        
        if (questions === 0) {
            alert('الرجاء إضافة سؤال واحد على الأقل');
            return false;
        }
        
        // Validate each question has at least one answer
        var valid = true;
        $('.aqs-question-item').each(function() {
            var questionText = $(this).find('textarea[name*="[text]"]').val().trim();
            var answers = $(this).find('.aqs-answer-option').length;
            
            if (!questionText) {
                alert('الرجاء إدخال نص السؤال');
                $(this).find('textarea[name*="[text]"]').focus();
                valid = false;
                return false;
            }
            
            if (answers < 2) {
                alert('كل سؤال يجب أن يحتوي على إجابتين على الأقل');
                valid = false;
                return false;
            }
            
            // Check if correct answer is selected
            var correctSelected = $(this).find('input[name*="[correct_answer]"]:checked').length;
            if (correctSelected === 0) {
                alert('الرجاء اختيار الإجابة الصحيحة');
                valid = false;
                return false;
            }
        });
        
        return valid;
    }
    
    // Delete module
    $(document).on('click', '.aqs-delete-module', function() {
        if (!confirm(aqsModuleData.strings.confirmDelete)) {
            return;
        }
        
        var $btn = $(this);
        var moduleId = $btn.data('id');
        var $row = $btn.closest('tr');
        
        $btn.prop('disabled', true).text('جاري الحذف...');
        
        $.ajax({
            url: aqsModuleData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aqs_delete_module',
                nonce: aqsModuleData.nonce,
                module_id: moduleId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data.message || aqsModuleData.strings.error);
                    $btn.prop('disabled', false).text('🗑️ حذف');
                }
            },
            error: function() {
                alert(aqsModuleData.strings.error);
                $btn.prop('disabled', false).text('🗑️ حذف');
            }
        });
    });
    
    // Initialize on page load
    if ($('.aqs-question-item').length > 0) {
        updateQuestionNumbers();
    }
});