class UploadImage {
    constructor (container, defaultsrc) {
        this.container = $(container);
        this.container.html(this.template());

        if(defaultsrc) {
            this.defaultsrc = defaultsrc;
        }

        this.initialize();
    }

    template () {
        let template = "";
        template += "<div class='mak-upload-image p-3'>"
        template +=     "<div class='select-image d-flex justify-content-center p-4'>"
        template +=         "<div style='font-size: 1rem'>Upload banner</div>"
        template +=         "<div style='font-size: 0.8rem'>JPG or PNG</div>"
        template +=         "<input type='file' class='hidden' id='image_file' />"
        template +=     "</div>"
        template +=     "<div class='uploaded-image d-flex flex-column align-items-center hidden'>"
        template +=         "<img src='' class='' />"
        template +=         "<input type='hidden' name='image' />"
        template +=         "<div class='mt-2 text-center remove-image'>Remove</div>"
        template +=         "<div class='upload-progress hidden'>"
        template +=             "<span class='spinner-border spinner-border-sm text-secondary' role='status'></span>"
        template +=             "<span class='up-value'></span>"
        template +=         "</div>"
        template +=     "</div>"
        template += "</div>"

        return template;
    }

    mychild(selector) {
        return this.container.find(selector);
    }

    initialize() {
        const theThis = this;

        // image_file_select_btn click event handler
        this.container.off('click', '.select-image');
        this.container.on('click', '.select-image', function(e) {
            e.preventDefault();
            theThis.mychild("#image_file").trigger('click');
        });

        // image_file_select event handler.
        this.container.off('change', '#image_file');
        this.container.on('change', '#image_file', function(e) {
            const file = e.target.files[0];
            if(!file) return;

            theThis.mychild(".select-image").addClass('hidden');
            theThis.mychild(".select-image").removeClass('d-flex');
            theThis.mychild(".uploaded-image").removeClass('hidden');
            theThis.mychild(".upload-progress").removeClass('hidden');

            const data = new FormData();
            data.append('folder', 'music');
            data.append('file', file);
            $.ajax({
                url: '/file/upload',
                type: 'post',
                data,
                processData: false,
                contentType: false,
                success: function(response) {
                    theThis.mychild("input[name='image']").val(response.path);
                    theThis.mychild("#audio_file")[0].value = '';
                },
                error: function(xhr, status, error) {
                    console.error('failed to send');
                    theThis.mychild(".mak-upload-audio .upload-progress").addClass('hidden');
                    theThis.mychild('.remove-image').prop('disabled', false);
                }
            })
        })
    }
}