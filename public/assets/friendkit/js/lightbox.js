"use strict";/*!lightbox.js | Friendkit | © Css Ninja. 2019-2020*/$((function () {
    if ($("[data-fancybox]").length) {
        var
            n = feather.icons["more-vertical"].toSvg(), s = feather.icons["thumbs-up"].toSvg(), a = feather.icons.lock.toSvg(), i = feather.icons.user.toSvg(), e = feather.icons.users.toSvg(), d = feather.icons.globe.toSvg(), t = feather.icons.heart.toSvg(), c = feather.icons.smile.toSvg(), o = feather.icons["message-circle"].toSvg(), l = `
<div class="header">
     <img style="display:none" src="/images/icons/user-icon.png" onerror="this.onerror=null;this.src='/images/icons/user-icon.png';" alt="" />
    <div class="user-meta">
         <span class="name"></span> <span><small class="post-date"></small></span>
    </div>

    <div class="dropdown is-spaced is-right dropdown-trigger">

        <div>

            <div class="button"> `+ n + `</div>

        </div>

        <div class="dropdown-menu" role="menu">

            <div class="dropdown-content">

                <div class="dropdown-item is-title has-text-left"> Who can see this ?</div>

                <a href="#" class="dropdown-item">

                    <div class="media">
                         `+ d + `
                        <div class="media-content">

                            <h3>Public</h3>
                             <small>Anyone can see this publication.</small>
                        </div>

                    </div>

                </a>

                <a class="dropdown-item">

                    <div class="media">
                         `+ e + `
                        <div class="media-content">

                            <h3>Friends</h3>
                             <small>only friends can see this publication.</small>
                        </div>

                    </div>

                </a>

                <a class="dropdown-item">

                    <div class="media">
                         `+ i + `
                        <div class="media-content">

                            <h3>Specific friends</h3>
                             <small>Don\'t show it to some friends.</small>
                        </div>

                    </div>

                </a>

                <hr class="dropdown-divider" />

                <a class="dropdown-item">

                    <div class="media">
                         `+ a + `
                        <div class="media-content">

                            <h3>Only me</h3>
                             <small>Only me can see this publication.</small>
                        </div>

                    </div>

                </a>

            </div>

        </div>

    </div>

</div>

<div class="inner-content">

    <div class="live-stats">

        <div class="social-count">

            <div class="likes-count"> `+ t + ` <span></span></div>



        </div>

        <div class="social-count ml-auto">

            <div class="views-count">
                 <span class="views-count-1"></span> <span class="views"><small>comments</small></span>
            </div>

        </div>

    </div>

    <div class="actions">

        <div class="action like-btn"> `+ s + ` <span>Like</span></div>

        <div class="action"> `+ o + ` <span>Comment</span></div>

    </div>

</div>




<div class="comments-list has-slimscroll">

</div>

<div class="comment-controls has-lightbox-emojis">

    <div class="controls-inner">
        <img style="display:none" src="" alt=""  onerror="this.onerror=null;this.src='/images/icons/user-icon.png';"/>
        <div class="control"> <textarea class="textarea comment-textarea is-rounded" rows="1"></textarea> <button class="emoji-button"> `+ c + `</button>
        <button class="send-comment"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>

</div>

`;

        $("[data-fancybox]").each((function () {
            if ("comments" == $(this).attr("data-lightbox-type")) {
                var
                    n = $(this).attr("data-fancybox"); console.log(n), $(this).fancybox({
                        baseClass: "fancybox-custom-layout", keyboard: !1, infobar: !1, touch: { vertical: !1 }, buttons: ["close", "thumbs"], animationEffect: "fade", transitionEffect: "fade", preventCaptionOverlap: !1, idleTime: !1, gutter: 0, caption: function (s) {
                            return "post1" == n ? l : "post2" == n ? v : "post3" == n ? m : "profile-post1" == n ? r : "profile-post2" == n ? p : "profile-post3" == n ? u : "profile-post4" == n ? g : void
                                0
                        }, afterShow: function (n, s) { initDropdowns(), initLightboxEmojis(), "development" === env && $(".fancybox-container [data-demo-src]").each((function () { var n = $(this).attr("data-demo-src"); $(this).attr("src", n) })) }
                    })
            }
        }))
    }
}));
