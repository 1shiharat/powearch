(function ($) {
    /**
     * メインクラス
     */
    var launcher = (new function () {

        /**
         * コンストラクタ
         */
        var self = function () {
            self.settings = {
                insertTarget: 'body',
                inputTarget: '.launcher__input',
                inputWrap: '.launcher',
                templateID: '#launcher_template'
            };

            self.keyUpState = 0;
            typeaheadInit();
            addEvent();
        };

        /**
         * typeahead を発動
         */
        var typeaheadInit = function () {

            var links = new Bloodhound({
                queryTokenizer: Bloodhound.tokenizers.whitespace,
                datumTokenizer: Bloodhound.tokenizers.whitespace,
                remote: {
                    url: powearchConfig.ajaxurl + '?action=launcher&q=%WILDCARD&nonce='+powearchConfig.nonce,
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
                },

            }, {
                name: 'wp_links',
                display: 'value',
                source: links,
                limit: 20,
                templates: {
                    empty: [
                        '<div class="Typeahead-suggestion Typeahead-selectable">',
                            powearchConfig.emptyMessage,
                        '</div>'
                    ].join('\n'),
                    suggestion: _.template( powearchConfig.template )
                }
            }).on('typeahead:cursorchange',function(ev,suggestion){

            }).on('typeahead:select',function(ev,suggestion){
                location.href = suggestion.link;
            });
        }

        /**
         * イベントを登録
         */
        var addEvent = function() {
            $(document).on('keyup', function(e){
                keyupEvent(e);
            });
            $(document).on('click', '#wp-admin-bar-powearch-toolbar',function(e){
                clickAdminBar(e);
            });
        }

        var clickAdminBar = function(e){
            e.preventDefault();
            activate();

        }

        var activate = function(){
            $(self.settings.inputWrap).addClass('active');
            $(self.settings.inputTarget).focus();
        }

        var deactivate = function(){
            $(self.settings.inputWrap).removeClass('active');
        }
        /**
         * キーアップ時のイベント
         * @param e
         */
        var keyupEvent = function (e){

            // press shift key

            if (e.keyCode == powearchConfig.trigger_key[0] || e.keyCode == powearchConfig.trigger_key[1]) {
                self.keyUpState++;

                if (e.keyCode == powearchConfig.trigger_key[1] && self.keyUpState > 1) {
                    if ($(self.settings.inputWrap).hasClass('active')) {
                        deactivate();
                    } else {
                        activate();
                    }
                    self.keyUpState = 0;
                }

            } else {
                self.keyUpState = 0;
            }

            // press esc key

            if (e.keyCode == 27) {
                if ($(self.settings.inputWrap).hasClass('active')) {
                    deactivate();
                }
            }

            self.activeLock = true;

            if ($(self.settings.inputWrap).hasClass('active') && self.activeLock == true) {
                self.activeLock = false;
                $(self.settings.inputTarget).addClass('flash').delay(300).queue(function (next) {
                    $(self.settings.inputTarget).removeClass('flash');
                    self.activeLock = true;
                    next();
                });
            }

            if ( self.keyupStateTimer ){
                clearTimeout( self.keyupStateTimer );
            }
            self.keyupStateTimer = setTimeout(function(){
                self.keyUpState = 0;
            },500);
        }
        return self;
    });

    $(function () {
        var launcherInstance = new launcher();

    })
})(jQuery);
