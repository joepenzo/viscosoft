'use strict';

var loadingCharacter = window.mc4wp_ajax_vars.loading_character || '\u00B7';

function getButtonText(button) {
    return button.innerHTML ? button.innerHTML : button.value;
}

function setButtonText(button, text) {
    button.innerHTML ? button.innerHTML = text : button.value = text;
}

function Loader(formElement) {
    this.form = formElement;
    this.button = formElement.querySelector('input[type="submit"], button[type="submit"]');
    this.loadingInterval = 0;

    if( this.button ) {
        this.originalButton = this.button.cloneNode(true);
    }
}

Loader.prototype.start = function() {
    if( this.button ) {
        // loading text
        var loadingText = this.button.getAttribute('data-loading-text');
        if( loadingText ) {
            setButtonText(this.button, loadingText);
            return;
        }

        // Show AJAX loader
        var styles = window.getComputedStyle( this.button );
        this.button.style.width = styles.width;
        setButtonText(this.button, loadingCharacter);
        this.loadingInterval = window.setInterval(this.tick.bind(this), 500 );
    } else {
        this.form.style.opacity = '0.5';
    }
};

Loader.prototype.tick = function() {
    // count chars, start over at 5
    var text = getButtonText(this.button);
    setButtonText(this.button, text.length >= 5 ? loadingCharacter : text + " " + loadingCharacter);
};


Loader.prototype.stop = function() {
    if( this.button ) {
        this.button.style.width = this.originalButton.style.width;
        var text = getButtonText(this.originalButton);
        setButtonText(this.button, text);
        window.clearInterval(this.loadingInterval);
    } else {
        this.form.style.opacity = '';
    }

};


module.exports = Loader;