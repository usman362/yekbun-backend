function getComments(data) {
    let comments = '';
    data.data.comments.forEach(function(data, index) {
        let child = '';
        if (data.child_comments.length > 0) {
            comments += `
                    <div class="media is-comment com_container" data-id="${data._id}">
                        <div class="comment-line"></div>
                        <figure class="media-left">
                            <p class="image is-32x32">
                                <img src="/storage/${data?.user?.image}" alt="" data-user-popover="${data?.user?.id}">
                            </p>
                        </figure>

                        <div class="media-content pb-0">
                            <div class="d-flex justify-content-between comment-actions mb-2" style="margin-top:-7px;">
                                <div class="username">${data?.user?.name} ${data?.user?.last_name}</div>
                                <span>${moment(data.created_at).fromNow()}</span>
                            </div>
                            <p class="mb-2">${data.comment}</p>
                        </div>
                        <a href="javascript:void(0)" class="comment-reply" data-username="@${data?.user?.username}" data-parent_id="${data._id}"><i class="fas fa-reply"></i></a>
                    </div>`;

            data.child_comments.forEach(function(child, index) {
                ++index;

                let height = 65;
                if (child.child_comments.length > 0) {
                    height += 85 * child.child_comments.length;
                    let commentLine2 =
                        `<div class="comment-line-2" style="height:calc(${height}px)"></div>`;
                    let commentLine3 =
                        '<div class="comment-line-3"></div>';
                    comments +=
                        `<div class="media is-comment is-nested com_container"  data-id="${child._id}">
                            ${index == data.child_comments.length ? '' : commentLine2}

                            ${commentLine3}

                            <div class="arrow-line 3"></div>
                            <figure class="media-left">
                                <p class="image is-32x32">
                                    <img src="/storage/${child?.user?.image}" alt="" data-user-popover="${child?.user?.id}">
                                </p>
                            </figure>

                            <div class="media-content pb-0">
                                <div class="d-flex justify-content-between comment-actions mb-2" style="margin-top:-7px;">
                                    <div class="username">${child?.user?.name} ${child?.user?.last_name}</div>
                                    <span>${moment(child?.created_at).fromNow()}</span>
                                </div>
                                <p class="mb-2">${child?.comment}</p>
                            </div>
                            <a href="javascript:void(0)" class="comment-reply" data-username="@${child?.user?.username}" data-parent_id="${child._id}"><i class="fas fa-reply"></i></a>
                        </div>`;
                    child.child_comments.forEach(function(childUltra,
                        index3) {
                        ++index3

                        let commentLine2 =
                            `<div class="comment-line-2" ></div>`;
                        comments +=
                            `<div class="media is-comment is-nested com_container" data-id="${childUltra._id}" style="margin-left: 38.5px !important">
                            ${index3 == child.child_comments.length ? '' : commentLine2}

                            <div class="arrow-line"></div>
                            <figure class="media-left">
                                <p class="image is-32x32">
                                    <img src="/storage/${childUltra?.user?.image}" alt="" data-user-popover="${childUltra?.user?.id}">
                                </p>
                            </figure>

                            <div class="media-content pb-0">
                                <div class="d-flex justify-content-between comment-actions mb-2" style="margin-top:-7px;">
                                    <div class="username">${childUltra?.user?.name} ${childUltra?.user?.last_name}</div>
                                    <span>${moment(childUltra?.created_at).fromNow()}</span>
                                </div>
                                <p class="mb-2">${childUltra?.comment}</p>
                            </div>
                            <a href="javascript:void(0)" class="comment-reply" data-username="@${childUltra?.user?.username}" data-parent_id="${childUltra.parent_id}"><i class="fas fa-reply"></i></a>
                        </div>`;
                    });

                } else {
                    let commentLine2 =
                        `<div class="comment-line-2" style="height:calc(${height}px)"></div>`;
                    comments += `<div class="media is-comment is-nested com_container"  data-id="${child._id}">

                            ${index == data.child_comments.length ? '' : commentLine2}

                            <div class="arrow-line"></div>
                            <figure class="media-left">
                                <p class="image is-32x32">
                                    <img src="/storage/${child?.user?.image}" alt="" data-user-popover="${child?.user?.id}">
                                </p>
                            </figure>

                            <div class="media-content pb-0">
                                <div class="d-flex justify-content-between comment-actions mb-2" style="margin-top:-7px;">
                                    <div class="username">${child?.user?.name} ${child?.user?.last_name}</div>
                                    <span>${moment(child?.created_at).fromNow()}</span>
                                </div>
                                <p class="mb-2">${child?.comment}</p>
                            </div>
                            <a href="javascript:void(0)" class="comment-reply" data-username="@${child?.user?.username}" data-parent_id="${child._id}"><i class="fas fa-reply"></i></a>
                        </div>`;
                }

            });
        } else {
            comments += `
                    <div class="media is-comment" data-id="${data._id}">
                        <figure class="media-left">
                            <p class="image is-32x32">
                                <img src="/storage/${data?.user?.image}" alt="" data-user-popover="${data?.user?.id}">
                            </p>
                        </figure>

                        <div class="media-content pb-0">
                            <div class="d-flex justify-content-between comment-actions mb-2" style="margin-top:-7px;">
                                <div class="username">${data?.user?.name} ${data?.user?.last_name}</div>
                                <span>${moment(data.created_at).fromNow()}</span>
                            </div>
                            <p class="mb-2">${data.comment}</p>
                        </div>
                        <a href="javascript:void(0)" class="comment-reply" data-username="@${data?.user?.username}" data-parent_id="${data._id}"><i class="fas fa-reply"></i></a>
                    </div>`;
        }


    });

    return comments;
}
