jQuery(function($){
    var form = $('form#ead-csv-import-form');
    if(!form.length) return;

    var fileInput = form.find('input[name="ead_csv_file"]');
    var previewWrap = $('<div id="ead-csv-preview-wrapper"></div>');
    var previewTable = $('<div id="ead-csv-preview"></div>');
    var mappingArea = $('<div id="ead-csv-mapping"></div>');
    var presetSelect = form.find('#mapping_preset');
    var saveNameInput = form.find('#save_mapping_name');
    var saveNameField = $('<input type="hidden" name="save_mapping_name" id="ead_save_mapping_name" />');
    var progressBar = $('<progress id="ead-import-progress" value="0" max="100"></progress>');
    var downloadBtn = $('<a id="ead-import-log-download" class="button" target="_blank">Download Log</a>');
    var confirmBtn = $('<button type="button" class="button button-primary" id="ead-confirm-mapping"></button>').text('Confirm Mapping & Import');
    var mappingField = $('<input type="hidden" id="ead_mapping_json" name="ead_mapping_json" />');

    var parsedLines = [];
    var headers = [];

    form.append(mappingField).append(saveNameField);
    fileInput.after(previewWrap.append(previewTable).append(mappingArea).append(progressBar).append(downloadBtn));

    var mappingPresets = window.eadMappingPresets || {};

    function populatePresets(){
        var pt = $('#import_post_type').val();
        presetSelect.empty().append($('<option value="">None</option>'));
        if(mappingPresets[pt]){
            $.each(mappingPresets[pt], function(name){
                presetSelect.append('<option value="'+name+'">'+name+'</option>');
            });
        }
    }
    populatePresets();
    $('#import_post_type').on('change', populatePresets);

    confirmBtn.on('click', function(){
        var mapping = {};
        $('#ead-csv-mapping').find('input.ead-mapping-input').each(function(){
            var col = $(this).data('column');
            mapping[col] = $(this).val();
        });
        mappingField.val(JSON.stringify(mapping));
        saveNameField.val(saveNameInput.val());
        progressBar.val(0).show();
        processChunks(mapping);
    });

    function parseLine(line){
        var result = [];
        var current = '';
        var inQuotes = false;
        for(var i=0;i<line.length;i++){
            var ch = line[i];
            if(ch === '"'){
                if(inQuotes && line[i+1] === '"'){
                    current += '"';
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
            } else if(ch === ',' && !inQuotes){
                result.push(current);
                current = '';
            } else {
                current += ch;
            }
        }
        result.push(current);
        return result;
    }

    function sanitizeKey(str){
        return str.toLowerCase().replace(/[^a-z0-9_]+/g,'_');
    }

    function buildPreview(h, rows){
        var table = $('<table class="ead-csv-preview-table"><thead></thead><tbody></tbody></table>');
        var hr = $('<tr></tr>');
        h.forEach(function(col){ hr.append('<th>'+col+'</th>'); });
        table.find('thead').append(hr);
        rows.forEach(function(row){
            var tr = $('<tr></tr>');
            h.forEach(function(col,i){
                tr.append('<td>'+(row[i]||'')+'</td>');
            });
            table.find('tbody').append(tr);
        });
        previewTable.empty().append(table);
    }

    function buildMapping(h){
        if(!$('#ead-meta-fields').length){
            var dl = $('<datalist id="ead-meta-fields"></datalist>');
            eadMetaFields.forEach(function(f){ dl.append('<option value="'+f+'">'); });
            $('body').append(dl);
        }
        var table = $('<table class="ead-csv-mapping-table"><tbody><tr></tr></tbody></table>');
        h.forEach(function(col){
            var key = sanitizeKey(col);
            var suggested = eadMetaFields.find(function(f){ return f === key; }) || key;
            var cell = $('<td></td>');
            var input = $('<input type="text" class="ead-mapping-input" list="ead-meta-fields">').val(suggested).attr('data-column', col);
            cell.append(input);
            table.find('tr').append(cell);
        });
        mappingArea.empty().append('<p>Adjust field mapping:</p>').append(presetSelect).append(table).append(saveNameInput).append(confirmBtn);
        presetSelect.off('change.ead').on('change.ead', function(){
            var name = $(this).val();
            var pt = $('#import_post_type').val();
            var preset = mappingPresets[pt] ? mappingPresets[pt][name] : null;
            if(preset){
                $('#ead-csv-mapping').find('input.ead-mapping-input').each(function(){
                    var col = $(this).data('column');
                    if(preset[col]) $(this).val(preset[col]);
                });
            }
        });
    }

    function processChunks(mapping){
        var totalRows = parsedLines.length - 1; // first row is header
        var chunkSize = 20;
        var index = 1; // start after header

        function send(){
            if(index > totalRows){
                progressBar.val(100);
                // final redirect to view logs
                window.location = window.location.href;
                return;
            }
            var chunk = parsedLines.slice(index, index + chunkSize);
            $.ajax({
                url: eadRestUrl + 'csv-import',
                method: 'POST',
                contentType: 'application/json',
                beforeSend: function(xhr){ xhr.setRequestHeader('X-WP-Nonce', eadRestNonce); },
                data: JSON.stringify({
                    headers: headers,
                    mapping: mapping,
                    rows: chunk,
                    offset: index - 1,
                    total: totalRows
                }),
                success: function(resp){
                    index += chunk.length;
                    var pct = Math.round((index-1)/totalRows*100);
                    progressBar.val(pct);
                    if(resp && resp.log_url){
                        downloadBtn.attr('href', resp.log_url).show();
                    }
                    send();
                },
                error: function(){
                    progressBar.hide();
                    alert('Import failed.');
                }
            });
        }
        send();
    }

    form.on('submit.eadCsv', function(e){
        if(mappingField.val()) return; // mapping already confirmed
        e.preventDefault();
        var file = fileInput[0].files[0];
        if(!file) return;
        var reader = new FileReader();
        reader.onload = function(ev){
            var text = ev.target.result.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
            parsedLines = text.split('\n').filter(function(l){ return l.trim().length; });
            if(!parsedLines.length) return;
            headers = parseLine(parsedLines[0]);
            var rows = [];
            for(var i=1;i<Math.min(3, parsedLines.length); i++){
                rows.push(parseLine(parsedLines[i]));
            }
            buildPreview(headers, rows);
            buildMapping(headers);
        };
        reader.readAsText(file);
    });
});
