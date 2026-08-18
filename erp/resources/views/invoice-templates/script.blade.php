<script>
$(document).ready(function() {
    let fieldCounter = 0;
    let currentSection = 'header';

    // Tab switching - remove any existing handlers first
    $('.tab').off('click').on('click', function(e) {      
        e.preventDefault();
        e.stopPropagation();
        
        const $this = $(this);
        const tabName = $this.data('tab');        
        const $container = $this.closest('.tabs'); 

        // Forcefully remove active from ALL tabs in this specific container
        $container.find('.tab').removeClass('active');
        
        // Add active to clicked tab
        $this.addClass('active');
        
        // Handle tab content
        $container.siblings('.tab-content').removeClass('active');
        $('#' + tabName).addClass('active');
        
        // Update current section for fields
        if (tabName.includes('fields')) {
            currentSection = tabName.replace('-fields', '');
        }
    });

    // Color picker sync
    $('#primary_color_picker').on('input', function() {
        $('#primary_color').val($(this).val());
        updatePreview();
    });

    $('#primary_color').on('input', function() {
        $('#primary_color_picker').val($(this).val());
        updatePreview();
    });

    $('#secondary_color_picker').on('input', function() {
        $('#secondary_color').val($(this).val());
        updatePreview();
    });

    $('#secondary_color').on('input', function() {
        $('#secondary_color_picker').val($(this).val());
        updatePreview();
    });

    $('#title_color_picker').on('input', function(){
        $('#title_color').val($(this).val());
        // updatePreview();
    });
    $('#title_color').on('input', function(){
        $('#title_color_picker').val($(this).val());
        // updatePreview();
    });
    $('#title_bg_picker').on('input', function(){
        $('#title_bg').val($(this).val());
        // updatePreview();
    });
    $('#title_bg').on('input', function(){
        $('#title_bg_picker').val($(this).val());
        // updatePreview();
    });
    $('#tabler_header_bg_picker').on('input', function(){
        $('#tabler_header_bg').val($(this).val());
        // updatePreview();
    });
    $('#tabler_header_bg').on('input', function(){
        $('#tabler_header_bg_picker').val($(this).val());
        // updatePreview();
    });
    $('#text_color_picker').on('input', function(){
        $('#text_color').val($(this).val());
        // updatePreview();
    });
    $('#text_color').on('input', function(){
        $('#text_color_picker').val($(this).val());
        // updatePreview();
    });

    // Add field button
    $('#add-field').on('click', function() {
        addField(currentSection);
    });

    // Add field function
    function addField(section = 'header', data = {}) {
        const template = $('#field-template').html();
        const $field = $(template);
        const fieldId = fieldCounter++;

        $field.attr('data-field-id', fieldId);
        $field.attr('data-section', section);

        // Set values
        if (data.label) $field.find('.field-label-input').val(data.label);
        if (data.key) $field.find('.field-key-input').val(data.key);
        if (data.type) $field.find('.field-type-input').val(data.type);
        if (data.section) $field.find('.field-section-input').val(data.section);
        if (data.is_required) $field.find('.field-required-input').prop('checked', true);
        if (data.is_visible !== false) $field.find('.field-visible-input').prop('checked', true);

        // Update label display
        const labelText = data.label || 'New Field';
        $field.find('.field-label').text(labelText);

        // Add to appropriate container
        $('#fields-' + section).append($field);

        // Event listeners
        $field.find('.field-label-input').on('input', function() {
            const label = $(this).val() || 'New Field';
            $field.find('.field-label').text(label);
            
            // Auto-generate key from label
            if (!$field.find('.field-key-input').val()) {
                const key = label.toLowerCase()
                    .replace(/[^a-z0-9]+/g, '_')
                    .replace(/^_|_$/g, '');
                $field.find('.field-key-input').val(key);
            }
            updatePreview();
        });

        $field.find('.field-key-input, .field-type-input, .field-section-input').on('change', function() {
            // Move field if section changed
            const newSection = $field.find('.field-section-input').val();
            if (newSection !== section) {
                $field.attr('data-section', newSection);
                $('#fields-' + newSection).append($field);
                section = newSection;
            }
            updatePreview();
        });

        $field.find('.field-required-input, .field-visible-input').on('change', function() {
            updatePreview();
        });

        $field.find('.remove-field').on('click', function() {
            $field.remove();
            updatePreview();
        });

        // Drag and drop
        $field.on('dragstart', function(e) {
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        });

        $field.on('dragend', function() {
            $(this).removeClass('dragging');
            updatePreview();
        });

        updatePreview();
    }

    // Drag and drop for fields
    $('.fields-container').on('dragover', function(e) {
        e.preventDefault();
        const dragging = $('.field-item.dragging');
        const afterElement = getDragAfterElement($(this), e.originalEvent.clientY);
        
        if (afterElement == null) {
            $(this).append(dragging);
        } else {
            $(this)[0].insertBefore(dragging[0], afterElement);
        }
    });

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.find('.field-item:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    // Form submission
    $('#save-template').on('click', function(e) {
        e.preventDefault();
        
        // Collect all fields
        const fields = [];
        let sortOrder = 0;

        $('.field-item').each(function() {
            const $field = $(this);
            const fieldData = {
                label: $field.find('.field-label-input').val(),
                key: $field.find('.field-key-input').val(),
                type: $field.find('.field-type-input').val(),
                section: $field.find('.field-section-input').val(),
                sort_order: sortOrder++,
                is_required: $field.find('.field-required-input').is(':checked') ? 1 : 0,
                is_visible: $field.find('.field-visible-input').is(':checked') ? 1 : 0
            };

            if (fieldData.label && fieldData.key) {
                fields.push(fieldData);
            }
        });

        // Add fields as JSON to form
        $('<input>').attr({
            type: 'hidden',
            name: 'fields',
            value: JSON.stringify(fields)
        }).appendTo('#template-form');

        // Submit form
        $('#template-form').submit();
    });

    // Update preview
    function updatePreview() {
        const paperSize = $('#paper_size').val() || 'A4';
        const orientation = $('#orientation').val() || 'portrait';
        const fontFamily = $('#font_family').val() || 'Inter';
        const primaryColor = $('#primary_color').val() || '#000000';
        const secondaryColor = $('#secondary_color').val() || '#6b7280';
        const showBorder = $('#show_border').is(':checked');
        const stripedTable = $('#striped_table').is(':checked');
        const zoomLevel = $('#zoom-level').val() || 0.75;

        let html = `
            <div class="preview-wrapper" style="transform: scale(${zoomLevel});">
                <div class="preview-paper ${paperSize} ${orientation}" style="font-family: ${fontFamily}; color: ${primaryColor};">
                    <div class="preview-header" style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid ${primaryColor};">
                        <h1 style="color: ${primaryColor}; margin: 0 0 20px 0; font-size: 28px;">INVOICE</h1>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        `;

        // Header fields
        const headerFields = getFieldsBySection('header');
        if (headerFields.length > 0) {
            html += '<div>';
            headerFields.forEach(field => {
                if (field.is_visible) {
                    html += `
                        <div style="margin-bottom: 10px;">
                            <strong style="color: ${secondaryColor}; font-size: 12px;">${field.label}:</strong>
                            <div style="font-size: 14px;">${getSampleValue(field.type)}</div>
                        </div>
                    `;
                }
            });
            html += '</div>';
        }

        html += `
                        </div>
                    </div>
                    
                    <div class="preview-body" style="margin-bottom: 30px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: ${primaryColor}; color: white;">
                                    <th style="padding: 12px; text-align: left; ${showBorder ? 'border: 1px solid #ddd;' : ''}">Item</th>
                                    <th style="padding: 12px; text-align: center; ${showBorder ? 'border: 1px solid #ddd;' : ''}">Qty</th>
                                    <th style="padding: 12px; text-align: right; ${showBorder ? 'border: 1px solid #ddd;' : ''}">Price</th>
                                    <th style="padding: 12px; text-align: right; ${showBorder ? 'border: 1px solid #ddd;' : ''}">Total</th>
                                </tr>
                            </thead>
                            <tbody>
        `;

        // Body fields (sample rows)
        for (let i = 1; i <= 3; i++) {
            const bgColor = stripedTable && i % 2 === 0 ? '#f9fafb' : 'transparent';
            html += `
                <tr style="background: ${bgColor};">
                    <td style="padding: 10px; ${showBorder ? 'border: 1px solid #ddd;' : ''}">Sample Item ${i}</td>
                    <td style="padding: 10px; text-align: center; ${showBorder ? 'border: 1px solid #ddd;' : ''}">${i}</td>
                    <td style="padding: 10px; text-align: right; ${showBorder ? 'border: 1px solid #ddd;' : ''}">$${(100 * i).toFixed(2)}</td>
                    <td style="padding: 10px; text-align: right; ${showBorder ? 'border: 1px solid #ddd;' : ''}">$${(100 * i * i).toFixed(2)}</td>
                </tr>
            `;
        }

        html += `
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="padding: 10px; text-align: right; font-weight: bold; ${showBorder ? 'border: 1px solid #ddd;' : ''}">Total:</td>
                                    <td style="padding: 10px; text-align: right; font-weight: bold; ${showBorder ? 'border: 1px solid #ddd;' : ''}">$1,400.00</td>
                                </tr>
                            </tfoot>
                        </table>
        `;

        // Body custom fields
        const bodyFields = getFieldsBySection('body');
        if (bodyFields.length > 0) {
            html += '<div style="margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
            bodyFields.forEach(field => {
                if (field.is_visible) {
                    html += `
                        <div>
                            <strong style="color: ${secondaryColor}; font-size: 12px;">${field.label}:</strong>
                            <div style="font-size: 14px;">${getSampleValue(field.type)}</div>
                        </div>
                    `;
                }
            });
            html += '</div>';
        }

        html += `
                    </div>
                    
                    <div class="preview-footer" style="padding-top: 20px; border-top: 1px solid #e5e7eb;">
        `;

        // Footer fields
        const footerFields = getFieldsBySection('footer');
        if (footerFields.length > 0) {
            footerFields.forEach(field => {
                if (field.is_visible) {
                    html += `
                        <div style="margin-bottom: 10px;">
                            <strong style="color: ${secondaryColor}; font-size: 12px;">${field.label}:</strong>
                            <div style="font-size: 14px;">${getSampleValue(field.type)}</div>
                        </div>
                    `;
                }
            });
        }

        html += `
                        <div style="text-align: center; margin-top: 30px; color: ${secondaryColor}; font-size: 12px;">
                            Thank you for your business!
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('#preview-container').html(html);
    }

    function getFieldsBySection(section) {
        const fields = [];
        $(`#fields-${section} .field-item`).each(function() {
            const $field = $(this);
            fields.push({
                label: $field.find('.field-label-input').val(),
                key: $field.find('.field-key-input').val(),
                type: $field.find('.field-type-input').val(),
                section: section,
                is_visible: $field.find('.field-visible-input').is(':checked')
            });
        });
        return fields;
    }

    function getSampleValue(type) {
        switch(type) {
            case 'number':
                return '12345';
            case 'date':
                return new Date().toLocaleDateString();
            case 'currency':
                return '$1,234.56';
            default:
                return 'Sample Text';
        }
    }

    // Listen to all form changes
    $('#template-form').on('change', 'input, select', function() {
        updatePreview();
    });

    $('#zoom-level').on('change', function() {
        updatePreview();
    });

    // Initial preview
    updatePreview();

    // Load existing fields if editing
    if (typeof existingFields !== 'undefined') {
        existingFields.forEach(field => {
            addField(field.section, field);
        });
    }

    // Add some default fields for new templates
    if (typeof existingFields === 'undefined') {
        
        // Header fields
        // addField('header', {
        //     label: 'Invoice Number',
        //     key: 'invoice_number',
        //     type: 'text',
        //     section: 'header',
        //     is_required: true,
        //     is_visible: true
        // });

        addField('header', {
            label: 'Invoice Date',
            key: 'invoice_date',
            type: 'date',
            section: 'header',
            is_required: true,
            is_visible: true
        });

        addField('body', {
            label: 'Customer Name',
            key: 'customer_name',
            type: 'text',
            section: 'body',
            is_required: true,
            is_visible: true
        });

        // Footer fields
        // addField('footer', {
        //     label: 'Payment Terms',
        //     key: 'payment_terms',
        //     type: 'text',
        //     section: 'footer',
        //     is_required: false,
        //     is_visible: true
        // });

        addField('footer', {
            label: 'Notes',
            key: 'notes',
            type: 'text',
            section: 'footer',
            is_required: false,
            is_visible: true
        });
    }
});    
</script>