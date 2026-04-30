$(document).ready(function () {
    const editModal = $("#editvotingModal");
    const createModal = $("#createvotingModal");

    // To open create-vote modal
    $(document).on('click', '.btn-create-vote', function (e) {
        let voteType = $(e.target).closest('button').data('vote-type');
        if (!voteType) return;

        const options_container = createModal.find(".allowed-reactions")[0];
        const hidden_div = createModal.find("#hidden_div")[0];
        const single_vote_options = createModal.find("#single-vote-options")[0];
        const individual_vote_options = createModal.find("#individual-vote-options")[0];

        if (voteType == 'single_vote') {
            createModal.find("input[name='vote_type']").val('single');
            createModal.find(".vote-header .title").text("Single Survey");
            hidden_div.appendChild(individual_vote_options);
            options_container.appendChild(single_vote_options);
        } else {
            createModal.find("input[name='vote_type']").val('individual');
            createModal.find(".vote-header .title").text("Individual Survey");
            hidden_div.appendChild(single_vote_options);
            options_container.appendChild(individual_vote_options);
        }
        Dropzone.forElement(".dropzone-img").removeAllFiles(true);
        Dropzone.forElement(".dropzone-view-img").removeAllFiles(true);
        let va = new UploadAudio(createModal.find('.vote-audio')[0]);

        createModal.modal('show');
    });

    // To open edit-vote modal
    $(document).on('click', '.btn-edit-vote', function (e) {
        const vote_id = $(e.target).closest('button').data('vote-id');
        const vote = votes.find(v => v._id == vote_id);
        editModal.find("input[name='vote_category_id']").val(vote.category_id);
        editModal.find("input[name='vote_type']").val(vote.vote_type);
        editModal.find("#editStatus").val(vote.status);
        editModal.find("input[name='id']").val(vote_id);
        editModal.find("#fullname").val(vote.name);
        editModal.find("#editForm").attr('action', `/surveys/${vote_id}`)
        Dropzone.forElement("#editForm .dropzone-img").removeAllFiles(true);
        Dropzone.forElement("#editForm .dropzone-view-img").removeAllFiles(true);
        $('.drop-img-edit1').css('height', '180px');
        $('.drop-img-edit2').css('height', '180px');
        $("#editStatus").trigger('change');

        const options_container = editModal.find(".allowed-reactions")[0];
        const hidden_div = editModal.find("#hidden_div")[0];
        const single_vote_options = editModal.find("#single-vote-options")[0];
        const individual_vote_options = editModal.find("#individual-vote-options")[0];

        // To select category
        editModal.find('.vote-categories div.vote-category').removeClass('selected');
        editModal.find(`.vote-categories div.vote-category[data-id='${vote.category_id}']`).addClass('selected');

        // To show banner
        const banner_container = editModal.find(".vote-banner.dropzone");
        banner_container.find("img")[1].src = base_url + vote.banner;

        const banner_view_container = editModal.find(".vote-banner.dropzone");
        banner_view_container.find("img")[0].src = base_url + vote.view_banner;

        if (vote.vote_type == 'individual') {
            editModal.find(".vote-header .title").text('Individual Vote')
            options_container.appendChild(individual_vote_options);
            hidden_div.appendChild(single_vote_options);
            for (let i = 0; i < 3; i++) {
                const option = $(individual_vote_options).find(`div.individual-reaction-option[data-index='${i}']`);
                let smImage = '';
                option.find("img")[0].src = base_url + vote.options[i].image;
                option.find(`input[name='reaction_option[${i}][title]']`).val(vote.options[i].title);
                console.log(vote.options[i].image);
                if (vote.options[i].image !== '' && vote.options[i].image !== null) {
                    option.find(`input[name='reaction_option[${i}][image]']`).val(vote.options[i].image);
                } else {
                    option.find(`input[name='reaction_option[${i}][image]']`).val('/assets/img/icons/others/6icon.png');
                }
            }
        } else {
            editModal.find(".vote-header .title").text('Single Vote')
            options_container.appendChild(single_vote_options);
            hidden_div.appendChild(individual_vote_options);
        }

        // To initialize UploadAudio instance.
        let va = new UploadAudio(editModal.find('.vote-audio')[0], vote.audio);

        editModal.modal('show');
    });

    editModal.on('submit', function () {

    });
    createModal.on('submit', function () {
        // const category_id = createModal.find("input[name='vote_category_id']").val();
        // if(!category_id) {
        //     toastr['warning']('', 'Please selecte category');
        //     return false;
        // }
        const view_banner = createModal.find("input[name='image']")[0];
        const banner = createModal.find("input[name='image']")[0];
        if (!banner && !view_banner) {
            toastr['warning']('', 'Please upload banner.');
            return false;
        }

        if ($('#is_valid_main').val() !== 'yes') {
            toastr['warning']('', 'Main Banner must be exactly 375x330 pixels.');
            return false;
        }

        if ($('#is_valid_view').val() !== 'yes') {
            toastr['warning']('', 'View Banner must be exactly 375x170 pixels.');
            return false;
        }
    })


    // To show statistic of a vote
    $(document).on('click', '.btn-statistic-vote', function (e) {
        const vote_id = $(e.target).closest('button').attr('data-vote-id');
        const vote_name = $(e.target).closest('button').attr('data-vote-name');
        $('#statisticVotingModal .modal-header h4').text(vote_name);
        $('.para-vote').text($(this).attr('data-vote-type'));
        $.ajax({
            url: `/surveys/${vote_id}/statistic`,
            success: function (response) {
                $("#statisticVotingModal .modal-body").html(response);
                $("#statisticVotingModal").modal('show');
            },
            error: function () {
            }
        })
    })

    // category selected event handler
    $(document).on('click', '.edit-vote-modal .vote-categories .vote-category', function (e) {
        // To remove .selected class from .vote-category
        $(".edit-vote-modal .vote-categories .vote-category").removeClass("selected");

        // To add .selected class to the selected category
        const div = $(e.target).closest('.vote-category').addClass('selected')

        $("input[name='vote_category_id']").val($(div).data('id'));
    });

    const previewTemplate = `<div class="row"><di class="col-md-12 d-flex justify-content-center"><div class="dz-preview dz-file-preview w-100">
                                <div class="dz-details">
                                <div class="dz-thumbnail">
                                    <img data-dz-thumbnail >
                                    <span class="dz-nopreview">No preview</span>
                                    <div class="dz-success-mark"></div>
                                    <div class="dz-error-mark"></div>
                                    <div class="dz-error-message"><span data-dz-errormessage></span></div>
                                    <div class="progress">
                                    <div class="progress-bar progress-bar-primary" role="progressbar" aria-valuemin="0" aria-valuemax="100" data-dz-uploadprogress></div>
                                    </div>
                                </div>
                                </div>
                            </div></div></di>`;

    // for image
    const dropzoneImages = $(".dropzone-img");
    for (let i = 0; i < dropzoneImages.length; i++) {
        const dropzoneMulti1 = new Dropzone(dropzoneImages[i], {
            url: '/file/upload',
            previewTemplate: previewTemplate,
            parallelUploads: 1,
            maxFilesize: 100,
            maxFiles: 1,
            acceptedFiles: 'image/*',
            addRemoveLinks: true,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            // ✅ Validate dimensions
            accept: function (file, done) {
                const _URL = window.URL || window.webkitURL;
                const img = new Image();
                img.onload = function () {
                    if (this.width === 375 && this.height === 330) {
                        done(); // valid
                        $('#is_valid_main').val('yes');
                    } else {
                        done("Image must be exactly 375x330 pixels."); // reject
                        $('#is_valid_main').val('no');
                    }
                };
                img.onerror = function () {
                    done("Not a valid image.");
                };
                img.src = _URL.createObjectURL(file);
            },
            sending: function (file, xhr, formData) {
                formData.append('folder', 'surveys');
            },
            success: function (file, response) {
                if (file.previewElement) {
                    file.previewElement.classList.add("dz-success");
                }
                file.previewElement.dataset.path = response.path;
                const hiddenInputsContainer = file.previewElement.closest('form').querySelector('.hidden-inputs');
                $('.dropzone-img .dz-thumbnail img').attr('src', base_url + response.path);
                $('.drop-img-edit1').css('height', '212px');
                hiddenInputsContainer.innerHTML += `<input type="hidden" name="image" value="${response.path}" data-path="${response.path}">`;
            },
            removedfile: function (file) {
                const previewElement = file.previewElement;

                // Remove hidden input only if file was uploaded
                if (previewElement && previewElement.dataset.path) {
                    const hiddenInputsContainer = previewElement.closest('form').querySelector('.hidden-inputs');
                    const input = hiddenInputsContainer.querySelector(`input[data-path="${previewElement.dataset.path}"]`);
                    if (input) input.remove();

                    // delete from storage if it was uploaded
                    $.ajax({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        url: '/file/delete',
                        method: 'delete',
                        data: { path: previewElement.dataset.path },
                        success: function () {
                            $('.drop-img-edit1').css('height', '180px');
                        }
                    });
                }

                // ✅ Always remove the preview from DOM (even for error files)
                if (previewElement != null && previewElement.parentNode != null) {
                    previewElement.parentNode.removeChild(previewElement);
                }

                return this._updateMaxFilesReachedClass();
            }
        });
    }

    const dropzoneViewImages = $(".dropzone-view-img");
    for (let i = 0; i < dropzoneViewImages.length; i++) {
        const dropzoneMulti2 = new Dropzone(dropzoneViewImages[i], {
            url: '/file/upload',
            previewTemplate: previewTemplate,
            parallelUploads: 1,
            maxFilesize: 100,
            maxFiles: 1,
            acceptedFiles: 'image/*',
            addRemoveLinks: true,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            // ✅ Validate dimensions
            accept: function (file, done) {
                const _URL = window.URL || window.webkitURL;
                const img = new Image();
                img.onload = function () {
                    if (this.width === 375 && this.height === 170) {
                        done(); // valid
                        $('#is_valid_view').val('yes');
                    } else {
                        done("Image must be exactly 375x170 pixels."); // reject
                        $('#is_valid_view').val('no');
                    }
                };
                img.onerror = function () {
                    done("Not a valid image.");
                };
                img.src = _URL.createObjectURL(file);
            },
            sending: function (file, xhr, formData) {
                formData.append('folder', 'surveys');
            },
            success: function (file, response) {
                if (file.previewElement) {
                    file.previewElement.classList.add("dz-success");
                }
                file.previewElement.dataset.path = response.path;
                const hiddenInputsContainer = file.previewElement.closest('form').querySelector('.hidden-inputs');
                $('.dropzone-view-img .dz-thumbnail img').attr('src', base_url + response.path);
                $('.drop-img-edit2').css('height', '212px');
                hiddenInputsContainer.innerHTML += `<input type="hidden" name="view_image" value="${response.path}" data-path="${response.path}">`;
            },
            removedfile: function (file) {
                const previewElement = file.previewElement;

                // Remove hidden input only if file was uploaded
                if (previewElement && previewElement.dataset.path) {
                    const hiddenInputsContainer = previewElement.closest('form').querySelector('.hidden-inputs');
                    const input = hiddenInputsContainer.querySelector(`input[data-path="${previewElement.dataset.path}"]`);
                    if (input) input.remove();

                    // delete from storage if it was uploaded
                    $.ajax({
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        url: '/file/delete',
                        method: 'delete',
                        data: { path: previewElement.dataset.path },
                        success: function () {
                            $('.drop-img-edit2').css('height', '180px');
                        }
                    });
                }

                // ✅ Always remove the preview from DOM (even for error files)
                if (previewElement != null && previewElement.parentNode != null) {
                    previewElement.parentNode.removeChild(previewElement);
                }

                return this._updateMaxFilesReachedClass();
            }

        });
    }

    $(document).on('click', '.individual-vote-react-option-image img', function (e) {
        const option = $(e.target).closest(".individual-reaction-option");
        option.find("input[type='file'").trigger('click');
    })

    $(document).on('change', ".individual-vote-react-option-image input[type='file']", function (e) {
        const file = e.target.files[0];
        if (!file) return;

        const option = $(e.target).closest('.individual-reaction-option');
        const index = option.data('index');

        const data = new FormData();
        data.append('folder', 'surveys');
        data.append('file', file);

        $.ajax({
            url: '/file/upload',
            type: 'post',
            data,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                option.find("img")[0].src = base_url+`${response.path}`;
                option.find(`input[name='reaction_option[${index}][image]']`).val(response.path);
                option.find(".individual-vote-react-option-image").addClass("uploaded");
                e.target.value = ''; // reset input so same file can be re-uploaded
            },
            error: function (xhr, status, error) {
                console.error('failed to send', error);
            }
        });
    });


    $(document).on('click', '.individual-vote-react-option-image .remove-image', function (e) {
        e.preventDefault();

        const option = $(e.target).closest('.individual-reaction-option');
        const index = option.data('index');
        const imagePath = option.find(`input[name='reaction_option[${index}][image]']`).val();

        $.ajax({
            url: '/file/delete',
            type: 'post', // use post + _method if Laravel
            data: { path: imagePath, _method: 'DELETE' },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                option.find("img")[0].src = '/assets/img/icons/others/6icon.png';
                option.find(".individual-vote-react-option-image").removeClass('uploaded');
                option.find(`input[name='reaction_option[${index}][image]']`).val('');
            },
            error: function (xhr, status, error) {
                console.error('failed to delete file', error);
            }
        });
    });

});
