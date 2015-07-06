(function ($) {
    var launcher = (new function () {

        var self = function () {

            self.settings = {
                insertTarget: 'body',
                inputTarget: '.launcher__input',
                inputWrap: '.launcher',
                templateID: '#launcher_template'
            };

            self.keyUpState = 0;
            searchFormInit();
            typeaheadInit();

            $(document).on('keyup',function(e){

                if (e.keyCode == 16 ){
                    self.keyUpState++;
                    if ( self.keyUpState > 1 ){
                        if ( $(self.settings.inputWrap).hasClass('active') ){
                            $(self.settings.inputWrap).removeClass('active');
                        } else {
                            $(self.settings.inputWrap).addClass('active');
                            $(self.settings.inputTarget).focus();
                        }
                        self.keyUpState = 0;
                    }
                } else {
                    self.keyUpState = 0;
                }
                if (e.keyCode == 27 ) {
                    if ( $(self.settings.inputWrap).hasClass('active') ){
                        $(self.settings.inputWrap).removeClass('active');
                    }
                }
                self.activeLock = true;
                if ( $(self.settings.inputWrap).hasClass('active') && self.activeLock == true ){
                    self.activeLock = false;
                    $(self.settings.inputTarget).addClass('flash').delay(300).queue(function(next) {
                        $(self.settings.inputTarget).removeClass('flash');
                        self.activeLock = true;
                        next();
                    });
                }

            })
        };

        var setTemplate = function () {
            self.template = $(self.settings.templateID).html();
            return self.template;
        }

        var compiled = function () {
            self.searchTemplate =self.template;
            return self.searchTemplate;
        }

        var insertSearchForm = function () {
            $(self.settings.insertTarget).append(self.searchTemplate);
        }

        var searchFormInit = function () {
            //setTemplate();
            //compiled();
            //insertSearchForm();
        }

        var substringMatcher = function (strs) {
            return function findMatches(q, cb) {
                var matches, substringRegex, substrRegex;
                matches = [];
                substrRegex = new RegExp(q, 'i');
                $.each(strs, function (i, str) {
                    if (substrRegex.test(str.value)) {
                        matches.push(str.value);
                    }
                });
                cb(matches);
            };
        };
        var typeaheadInit = function () {

            var links = new Bloodhound({
                queryTokenizer: Bloodhound.tokenizers.whitespace,
                datumTokenizer: Bloodhound.tokenizers.whitespace,
                remote: {
                    url: wpaTypeaheadConfig.ajaxurl + '?action=launcher&q=%WILDCARD&nonce='+wpaTypeaheadConfig.nonce,
                    wildcard: '%WILDCARD',
                    dataType: 'json',
                    timeout: 3000,
                    cache: true
                }
            });

            $(self.settings.inputTarget).typeahead({
                minLength: 0,
                highlight: true,
                maxLength: 10,
                classNames: {
                    open: 'is-open',
                    empty: 'is-empty',
                    cursor: 'is-active',
                    suggestion: 'Typeahead-suggestion',
                    selectable: 'Typeahead-selectable'
                }
            }, {
                name: 'wp_links',
                display: 'value',
                source: links,
                limit: 20
            }).on('typeahead:cursorchange',function(ev,suggestion){

            }).on('typeahead:select',function(ev,suggestion){
                location.href = suggestion.link;
            });
        }


        return self;
    });

    $(function () {
        var launcherInstance = new launcher();

    })
})(jQuery);
