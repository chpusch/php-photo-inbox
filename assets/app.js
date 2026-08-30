/**
 * Foto-Postfach - progressive Verbesserung des Upload-Formulars.
 *
 * Ohne JavaScript funktioniert das Formular als normaler POST. Dieses Skript
 * ergaenzt Drag & Drop, Vorschaubilder, eine Vorpruefung im Browser und einen
 * Upload mit Fortschrittsanzeige. Die serverseitige Pruefung bleibt davon
 * unberuehrt - hier passiert nur Komfort, keine Sicherheit.
 */
(function () {
    'use strict';

    var form = document.querySelector('.uploader');
    if (!form) {
        return;
    }

    // Ohne diese Bausteine bleibt es beim klassischen Formular-Post.
    var supported = typeof window.DataTransfer === 'function' &&
        typeof window.FormData === 'function' &&
        typeof window.XMLHttpRequest === 'function' &&
        'upload' in new XMLHttpRequest();

    if (!supported) {
        return;
    }

    var input = form.querySelector('.dropzone__input');
    var zone = form.querySelector('[data-dropzone]');
    var queue = form.querySelector('[data-queue]');
    var progress = form.querySelector('[data-progress]');
    var progressBar = form.querySelector('[data-progress-bar]');
    var progressLabel = form.querySelector('[data-progress-label]');
    var submit = form.querySelector('[data-submit]');
    var submitLabel = form.querySelector('[data-submit-label]');
    var clearButton = form.querySelector('[data-clear]');
    var card = form.closest('.card');

    var maxBytes = parseInt(form.dataset.maxBytes, 10) || 0;
    var maxFiles = parseInt(form.dataset.maxFiles, 10) || 0;
    var accepted = (form.dataset.accept || '').split(',').filter(Boolean);

    var previews = [];
    var busy = false;

    /* ---------------------------------------------------------------- Utils */

    function formatBytes(bytes) {
        if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
        }
        if (bytes >= 1024) {
            return Math.round(bytes / 1024) + ' KB';
        }
        return bytes + ' Bytes';
    }

    /**
     * Grobe Vorpruefung im Browser. Der Typ kommt vom Betriebssystem und ist
     * nicht vertrauenswuerdig - HEIC meldet mancher Browser gar nicht, daher
     * wird ein leerer Typ hier durchgelassen und erst am Server entschieden.
     */
    function validate(file) {
        if (maxBytes > 0 && file.size > maxBytes) {
            return 'zu groß (max. ' + formatBytes(maxBytes) + ')';
        }
        if (file.size === 0) {
            return 'Datei ist leer';
        }
        if (file.type && accepted.length && accepted.indexOf(file.type) === -1) {
            return 'Format wird nicht unterstützt';
        }
        return null;
    }

    function releasePreviews() {
        previews.forEach(function (url) {
            URL.revokeObjectURL(url);
        });
        previews = [];
    }

    /* ------------------------------------------------------------ Dateiliste */

    function currentFiles() {
        return Array.prototype.slice.call(input.files || []);
    }

    function setFiles(files) {
        var transfer = new DataTransfer();
        files.slice(0, maxFiles > 0 ? maxFiles : files.length).forEach(function (file) {
            transfer.items.add(file);
        });
        input.files = transfer.files;
        render();
    }

    function addFiles(incoming) {
        var existing = currentFiles();
        var signatures = existing.map(function (file) {
            return file.name + ':' + file.size + ':' + file.lastModified;
        });

        Array.prototype.forEach.call(incoming, function (file) {
            var signature = file.name + ':' + file.size + ':' + file.lastModified;
            if (signatures.indexOf(signature) === -1) {
                signatures.push(signature);
                existing.push(file);
            }
        });

        setFiles(existing);
    }

    function removeFile(index) {
        var files = currentFiles();
        files.splice(index, 1);
        setFiles(files);
    }

    function render() {
        var files = currentFiles();

        releasePreviews();
        queue.textContent = '';
        queue.hidden = files.length === 0;
        clearButton.hidden = files.length === 0;

        files.forEach(function (file, index) {
            queue.appendChild(buildQueueItem(file, index));
        });

        // Beanstandete Dateien blockieren den Vorgang nicht - sie werden nur
        // nicht mitgeschickt. Gesperrt wird erst, wenn nichts Gueltiges uebrig ist.
        var sendable = files.filter(function (file) {
            return validate(file) === null;
        }).length;

        submit.disabled = busy || sendable === 0;
        submitLabel.textContent = sendable > 0
            ? (sendable === 1 ? '1 Bild hochladen' : sendable + ' Bilder hochladen')
            : 'Hochladen';
    }

    function buildQueueItem(file, index) {
        var problem = validate(file);

        var item = document.createElement('li');
        item.className = 'queue__item' + (problem ? ' queue__item--invalid' : '');

        var thumb = document.createElement('img');
        thumb.className = 'queue__thumb';
        thumb.alt = '';
        thumb.loading = 'lazy';
        if (file.type && file.type.indexOf('image/') === 0) {
            var url = URL.createObjectURL(file);
            previews.push(url);
            // Laesst sich die Datei nicht darstellen, bleibt die neutrale
            // Platzhalterflaeche stehen statt eines kaputten Bildsymbols.
            thumb.addEventListener('error', function () {
                thumb.removeAttribute('src');
            });
            thumb.src = url;
        }
        item.appendChild(thumb);

        var body = document.createElement('div');
        body.className = 'queue__body';

        var name = document.createElement('span');
        name.className = 'queue__name';
        name.textContent = file.name;   // textContent, nie innerHTML
        name.title = file.name;
        body.appendChild(name);

        var meta = document.createElement('span');
        meta.className = 'queue__meta';
        meta.textContent = problem ? problem : formatBytes(file.size);
        body.appendChild(meta);

        item.appendChild(body);

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'queue__remove';
        remove.textContent = '\u00d7';
        remove.setAttribute('aria-label', file.name + ' entfernen');
        remove.addEventListener('click', function () {
            removeFile(index);
        });
        item.appendChild(remove);

        return item;
    }

    /* ------------------------------------------------------------ Drag & Drop */

    ['dragenter', 'dragover'].forEach(function (type) {
        zone.addEventListener(type, function (event) {
            event.preventDefault();
            zone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach(function (type) {
        zone.addEventListener(type, function (event) {
            event.preventDefault();
            if (type === 'drop' || !zone.contains(event.relatedTarget)) {
                zone.classList.remove('is-dragover');
            }
        });
    });

    zone.addEventListener('drop', function (event) {
        if (event.dataTransfer && event.dataTransfer.files.length) {
            addFiles(event.dataTransfer.files);
        }
    });

    // Ausserhalb der Zone fallengelassene Bilder sollen den Browser nicht
    // dazu bringen, die Datei einfach anzuzeigen.
    ['dragover', 'drop'].forEach(function (type) {
        document.addEventListener(type, function (event) {
            if (!zone.contains(event.target)) {
                event.preventDefault();
            }
        });
    });

    input.addEventListener('change', render);
    clearButton.addEventListener('click', function () {
        setFiles([]);
        input.focus();
    });

    /* ---------------------------------------------------------------- Upload */

    form.addEventListener('submit', function (event) {
        var files = currentFiles();
        if (busy || files.length === 0) {
            return;
        }

        event.preventDefault();

        var sendable = [];
        var rejectedHere = [];

        files.forEach(function (file) {
            var problem = validate(file);
            if (problem) {
                rejectedHere.push({ originalName: file.name, reason: problem });
            } else {
                sendable.push(file);
            }
        });

        if (sendable.length === 0) {
            showResults({ rejected: rejectedHere });
            return;
        }

        // FormData wird von Hand gefuellt, damit die beanstandeten Dateien
        // gar nicht erst uebertragen werden.
        var payload = new FormData();
        form.querySelectorAll('input[type="hidden"]').forEach(function (field) {
            payload.append(field.name, field.value);
        });
        sendable.forEach(function (file) {
            payload.append('files[]', file, file.name);
        });

        send(payload, rejectedHere);
    });

    function send(payload, rejectedHere) {
        var request = new XMLHttpRequest();

        busy = true;
        submit.disabled = true;
        progress.hidden = false;
        setProgress(0, 'Upload wird vorbereitet …');

        request.upload.addEventListener('progress', function (event) {
            if (!event.lengthComputable) {
                return;
            }
            var percent = Math.round((event.loaded / event.total) * 100);
            setProgress(percent, percent < 100
                ? 'Upload läuft … ' + percent + ' %'
                : 'Bilder werden geprüft …');
        });

        request.addEventListener('load', function () {
            var result = null;
            try {
                result = JSON.parse(request.responseText);
            } catch (error) {
                result = null;
            }

            finish(result || {
                errors: ['Unerwartete Antwort vom Server (Status ' + request.status + ').']
            }, rejectedHere);
        });

        request.addEventListener('error', function () {
            finish({ errors: ['Die Verbindung wurde unterbrochen. Bitte erneut versuchen.'] }, rejectedHere);
        });

        request.addEventListener('abort', function () {
            finish({ errors: ['Der Upload wurde abgebrochen.'] }, rejectedHere);
        });

        request.open('POST', form.action || window.location.href);
        request.setRequestHeader('X-Requested-With', 'fetch');
        request.setRequestHeader('Accept', 'application/json');
        request.send(payload);
    }

    function setProgress(percent, label) {
        progressBar.style.width = percent + '%';
        progressLabel.textContent = label;
    }

    function finish(result, rejectedHere) {
        busy = false;
        progress.hidden = true;
        setProgress(0, '');

        // Lokal aussortierte Dateien gehoeren mit in den Bericht.
        result.rejected = (rejectedHere || []).concat(result.rejected || []);

        if ((result.saved || []).length > 0) {
            setFiles([]);
        } else {
            render();
        }

        showResults(result);
    }

    /* ------------------------------------------------------------- Ergebnis */

    function showResults(result) {
        var previousBlock = card.querySelector('.results');
        if (previousBlock) {
            previousBlock.remove();
        }

        var block = document.createElement('div');
        block.className = 'results';
        block.setAttribute('role', 'status');
        block.setAttribute('aria-live', 'polite');

        (result.errors || []).forEach(function (message) {
            block.appendChild(buildAlert('error', '!', message));
        });

        var saved = result.saved || [];
        if (saved.length > 0) {
            block.appendChild(buildAlert(
                'success',
                '✓',
                saved.length === 1 ? '1 Bild wurde gespeichert.' : saved.length + ' Bilder wurden gespeichert.',
                saved.map(function (file) {
                    return { name: file.originalName, meta: formatBytes(file.bytes), tag: file.formatLabel };
                })
            ));
        }

        var rejected = result.rejected || [];
        if (rejected.length > 0) {
            block.appendChild(buildAlert(
                'warning',
                '×',
                rejected.length === 1 ? '1 Datei wurde abgelehnt.' : rejected.length + ' Dateien wurden abgelehnt.',
                rejected.map(function (file) {
                    return { name: file.originalName, meta: file.reason };
                })
            ));
        }

        if (!block.children.length) {
            return;
        }

        card.insertBefore(block, card.firstChild);
        block.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function buildAlert(variant, symbol, message, entries) {
        var alert = document.createElement('div');
        alert.className = 'alert alert--' + variant;

        var icon = document.createElement('span');
        icon.className = 'alert__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = symbol;
        alert.appendChild(icon);

        var body = document.createElement('div');
        var text = document.createElement('p');
        text.textContent = message;
        body.appendChild(text);

        if (entries && entries.length) {
            var list = document.createElement('ul');
            list.className = 'filelist';

            entries.forEach(function (entry) {
                var row = document.createElement('li');

                var name = document.createElement('span');
                name.className = 'filelist__name';
                name.textContent = entry.name;
                row.appendChild(name);

                if (entry.tag) {
                    var tag = document.createElement('span');
                    tag.className = 'tag';
                    tag.textContent = entry.tag;
                    row.appendChild(tag);
                }

                var meta = document.createElement('span');
                meta.className = 'filelist__meta';
                meta.textContent = entry.meta;
                row.appendChild(meta);

                list.appendChild(row);
            });

            body.appendChild(list);
        }

        alert.appendChild(body);
        return alert;
    }

    render();
}());
