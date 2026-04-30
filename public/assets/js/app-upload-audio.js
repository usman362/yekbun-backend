class UploadAudio {
    constructor(container, defaultSrc) {
        this.container = $(container);
        this.container.html(this.template());

        if(defaultSrc) {
            this.audioSrc = defaultSrc;
            this.container.find("#audio_select_panel").addClass("hidden");
            this.container.find("#audio_select_panel").removeClass("d-flex");

            this.container.find("#audio_detail_panel").removeClass("hidden");

            this.container.find("input[name='audio']").val(defaultSrc);
            this.container.find("#audio_filename").text(defaultSrc);

            this.container.find(".mak-upload-audio .upload-progress").addClass('hidden');
            this.container.find('.mak-upload-audio #remove_audio_btn').prop('disabled', false);

            this.container.find("#audio_player")[0].src = defaultSrc;
        }

        this.initialize();
    }

    template() {
        let template = "";
        template += "<div class='mak-upload-audio p-3'>"
        template +=     "<div class='d-flex justify-content-center align-items-center' id='audio_select_panel'>"
        template +=         "<button class='btn p-0' id='select_audio_btn'>"
        template +=             "<img src='/assets/svg/icons/upload.svg' width='30' />"
        template +=             "<span class='ms-2 label-upload-audio'>Upload Audio</span>"
        template +=         "</button>"
        template +=         "<input type='file' class='hidden' id='audio_file' name='audio_file' accept='.mp3,.wav,.ogg'/>"
        template +=         "<input type='hidden' name='audio' />"
        template +=     "</div>"
        template +=     "<div class='hidden' id='audio_detail_panel'>"
        template +=         "<div class='d-flex align-items-center mt-2 w-100 audio-meta'>"
        template +=             "<div class='flex-fluid d-flex align-items-center'>"
        template +=                 "<div><img src='/assets/img/icons/others/music.png' /></div>"
        template +=                 "<div class='ms-4'>"
        template +=                     "<div id='audio_filename'></div>"
        template +=                     "<div><span id='audio_file_size'></span></div>"
        template +=                 "</div>"
        template +=             "</div>"
        template +=             "<div class='justify-content-end upload-progress hidden'>"
        template +=                 "<span class='spinner-border spinner-border-sm text-secondary' role='status'></span>"
        template +=                 "<span class='up-value'></span>"
        template +=             "</div>"
        template +=         "</div>"
        template +=         "<div class='action-bar mt-2 d-flex'>"
        template +=             "<div class='has-splitter-bar right d-flex justify-content-center align-items-center'>"
        template +=                 "<button class='btn p-0' id='upload_btn'>Upload Audio</button>"
        template +=             "</div>"
        template +=             "<div class='flex-fluid d-flex align-items-center justify-content-center p-1'>"
        template +=                 "<audio controls id='audio_player'></audio>"
        template +=             "</div>"
        template +=             "<div class='has-splitter-bar left d-flex align-items-center h-100'>"
        template +=                 "<button class='btn p-0' id='remove_audio_btn'>"
        template +=                     "<i class='bx bx-trash bx-sm'></i>"
        template +=                 "</button>"
        template +=             "</div>"
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

        // audio_file_select_btn click event handler
        this.container.off('click', '.mak-upload-audio #select_audio_btn');
        this.container.on('click', '.mak-upload-audio #select_audio_btn', function(e) {
            e.preventDefault();
            theThis.mychild("#audio_file").trigger('click');
        })

        // audio_file_select event handler.
        this.container.off('change', '.mak-upload-audio #audio_file');
        this.container.on('change', '.mak-upload-audio #audio_file', function(e) {
            const file = e.target.files[0];
            if(!file) return;

            theThis.mychild("#audio_detail_panel").removeClass("hidden");
            theThis.mychild("#audio_file_size").html(formatFileSize(file.size));
            theThis.mychild("#audio_filename").html(file.name);

            const url = URL.createObjectURL(file);
            theThis.mychild("#audio_player")[0].src = url;

            theThis.mychild('.mak-upload-audio #upload_btn').prop('disabled', false);
        })

        // To remove audio
        this.container.off('click', '.mak-upload-audio #remove_audio_btn');
        this.container.on('click', '.mak-upload-audio #remove_audio_btn', function(e) {
            e.preventDefault();
            theThis.mychild('#audio_file')[0].files = null;

            const uploaded_audio_path = theThis.mychild("input[name='audio']").val();
            if(uploaded_audio_path) {
                const data = {
                    path: uploaded_audio_path
                }
                $.ajax({
                    url: '/file/delete',
                    type: 'delete',
                    data,
                    success: function(response) {
                        theThis.mychild("input[name='audio']").val('');
                        theThis.mychild("#audio_select_panel").removeClass("hidden");
                        theThis.mychild("#audio_select_panel").addClass("d-flex");
                        theThis.mychild("#audio_detail_panel").addClass("hidden");
                    },
                    error: function(xhr, status, error) {
                        console.error('failed to delete file');
                    }
                })
            } else {
                theThis.mychild("#audio_select_panel").removeClass("hidden");
                theThis.mychild("#audio_select_panel").addClass("d-flex");
                theThis.mychild("#audio_detail_panel").addClass("hidden");
            }
        })

        // To upload audio
        this.container.off('click', '.mak-upload-audio #upload_btn');
        this.container.on('click', '.mak-upload-audio #upload_btn', function(e) {
            e.preventDefault();
            const file = theThis.mychild(".mak-upload-audio #audio_file")[0].files[0];
            if(!file) return;

            theThis.mychild(".mak-upload-audio .upload-progress").removeClass('hidden');
            theThis.mychild('.mak-upload-audio #remove_audio_btn').prop('disabled', true);
            theThis.mychild('.mak-upload-audio #upload_btn').prop('disabled', true);

            const data = new FormData();
            data.append('folder', 'music');
            data.append('file', file);
            $.ajax({
                url: '/file/upload',
                type: 'post',
                data,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();

                    xhr.upload.addEventListener("progress", function(evt) {
                        if (evt.lengthComputable) {
                            var percentComplete = parseInt(evt.loaded * 100 / evt.total) ;
                            theThis.mychild(".mak-upload-audio .upload-progress .up-value").text(`${percentComplete}%`)
                        }
                    }, false);

                    return xhr;
                },
                success: function(response) {
                    theThis.mychild("input[name='audio']").val(response.path);
                    theThis.mychild("#audio_select_panel").addClass('hidden');
                    theThis.mychild("#audio_select_panel").removeClass('d-flex');
                    theThis.mychild(".mak-upload-audio .upload-progress").addClass('hidden');
                    theThis.mychild('.mak-upload-audio #remove_audio_btn').prop('disabled', false);

                    theThis.mychild(".mak-upload-audio #audio_file")[0].value = '';
                },
                error: function(xhr, status, error) {
                    console.error('failed to send');
                    theThis.mychild(".mak-upload-audio .upload-progress").addClass('hidden');
                    theThis.mychild('.mak-upload-audio #remove_audio_btn').prop('disabled', false);
                    theThis.mychild('.mak-upload-audio #upload_btn').prop('disabled', false);
                }
            })
        })
    }
}
