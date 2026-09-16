// Permet de glisser-déposer ou coller une image directement dans l'éditeur
// riche (Trix) du champ "Contenu détaillé" des pages, sans système de blocs
// séparé : l'image est uploadée en AJAX puis insérée dans le texte à l'endroit
// où elle a été déposée.
//
// Point important : tant que l'upload n'est pas terminé, l'image n'existe nulle
// part dans le contenu sérialisé de l'éditeur (Trix ne l'ajoute au champ caché
// qu'une fois attachment.setAttributes({url, ...}) appelé). Si on enregistre le
// formulaire avant la fin de l'envoi, l'image disparaît donc silencieusement.
// On désactive donc les boutons de sauvegarde tant qu'un upload est en cours.
(function () {
    let pendingUploads = 0;

    function findSubmitButtons(trixElement) {
        const form = trixElement.closest('form');
        if (!form) {
            return [];
        }
        // EasyAdmin place les boutons d'action (ex: "Sauvegarder les modifications")
        // en dehors de la balise <form>, reliés uniquement via l'attribut form="...".
        const nested = [...form.querySelectorAll('button[type="submit"]')];
        const external = form.id
            ? [...document.querySelectorAll('button[type="submit"][form="' + CSS.escape(form.id) + '"]')]
            : [];
        return [...new Set([...nested, ...external])];
    }

    function setUploading(trixElement, uploading) {
        pendingUploads += uploading ? 1 : -1;
        if (pendingUploads < 0) {
            pendingUploads = 0;
        }

        const buttons = findSubmitButtons(trixElement);
        buttons.forEach(function (button) {
            if (pendingUploads > 0) {
                if (!button.dataset.originalLabel) {
                    button.dataset.originalLabel = button.innerHTML;
                }
                button.disabled = true;
                button.innerHTML = "Envoi de l'image en cours...";
            } else {
                button.disabled = false;
                if (button.dataset.originalLabel) {
                    button.innerHTML = button.dataset.originalLabel;
                    delete button.dataset.originalLabel;
                }
            }
        });
    }

    document.addEventListener('trix-attachment-add', function (event) {
        const attachment = event.attachment;
        const trixElement = event.target;

        if (!attachment.file) {
            return;
        }

        setUploading(trixElement, true);

        const formData = new FormData();
        formData.append('file', attachment.file);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/admin/content-image-upload', true);

        xhr.upload.addEventListener('progress', function (progressEvent) {
            if (progressEvent.lengthComputable) {
                attachment.setUploadProgress((progressEvent.loaded / progressEvent.total) * 100);
            }
        });

        xhr.addEventListener('load', function () {
            setUploading(trixElement, false);

            if (xhr.status >= 200 && xhr.status < 300) {
                const data = JSON.parse(xhr.responseText);
                attachment.setAttributes({
                    url: data.url,
                    href: data.url,
                });
            } else {
                let message = "Échec de l'envoi de l'image.";
                try {
                    message = JSON.parse(xhr.responseText).error || message;
                } catch (e) {
                    // ignore
                }
                attachment.remove();
                alert(message);
            }
        });

        xhr.addEventListener('error', function () {
            setUploading(trixElement, false);
            attachment.remove();
            alert("Échec de l'envoi de l'image (erreur réseau).");
        });

        xhr.send(formData);
    });
})();
