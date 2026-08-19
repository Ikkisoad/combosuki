import 'bootstrap/dist/js/bootstrap.bundle.min.js';

window.returnColor = function (color) {
    document.getElementById('headcolor').value = color;
};

window.copytoclip = function (link) {
    const dummy = document.createElement('textarea');
    document.body.appendChild(dummy);
    dummy.value = link;
    dummy.select();
    document.execCommand('copy');
    document.body.removeChild(dummy);
};

window.playVideo = function (videoId) {
    const video = document.getElementById(videoId);
    video.src = video.dataset.src;
};

window.resetVideo = function (videoId) {
    const video = document.getElementById(videoId);
    const src = video.src.replace('?autoplay=1', '');
    video.src = '';
    video.dataset.src = src;
};

window.moveNumbers = function (num) {
    const textarea = document.getElementById('comboarea');
    textarea.value = textarea.value + num + ' ';
};

window.backspace = function () {
    const textarea = document.getElementById('comboarea');
    let txt = textarea.value;
    if (txt.length === 0) {
        return;
    }
    if (txt[txt.length - 1] === ' ') {
        txt = txt.substring(0, txt.length - 1);
    }
    while (txt.length > 0 && txt[txt.length - 1] !== ' ') {
        txt = txt.substring(0, txt.length - 1);
    }
    textarea.value = txt;
};

window.change_display = function () {
    const line = document.getElementById('combo_line');
    const text = document.getElementById('combo_text');
    const temp = line.innerHTML;
    line.innerHTML = text.innerHTML;
    text.innerHTML = temp;
};

window.showDIV = function (divId) {
    const el = document.getElementById(divId);
    if (el.style.display === 'none') {
        el.style.display = 'block';
        window.playVideo('v' + divId);
    } else {
        el.style.display = 'none';
        window.resetVideo('v' + divId);
    }
};
