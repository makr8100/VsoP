/**
 * hash.js - parse HTML hash and convert to JavaScript object, then send AJAX request
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-12-16
 * @package      VsoP
 * @name         hash.js
 * @since        2019-06-24
 * @version      0.14
 * @license      MIT
 */

var poll = {};
var vueobj = {};
var vuemsg = {};
var ajax = {};
var loginConfig = {};
var h = parseHash();
var filterTimeout = null;

const vueMethods = {
    h: function(check) {
        return typeof h[check] !== 'undefined' ? h[check] : false;
    },
    back: function(param) {
        window.history.back();
        if (typeof param !== 'undefined') window.onpopstate = function() {
            if (typeof h.action !== 'undefined') delete h.action;
            vueMethods.clearParam(param, true);
        };
    },
    edit: function(view, param, key) {
        var params = {
            view: view,
            action: 'edit'
        };
        params[param] = key;
        window.location = buildHash(params);
    },
    checkEdit: function() {
        return (this.h('action') === 'edit');
    },
    setParam: function(param, val) {
        if ($('#' + param).length) {
            $('#' + param).val(val).trigger('input');
        } else {
            var newHash = {};
            newHash[param] = val;
            window.location = buildHash(newHash);
        }
    },
    clearParam: function(param, destroyPop) {
        $('#' + param).val('').trigger('input');
        if (typeof destroyPop !== 'undefined' && destroyPop) window.onpopstate = null;
    },
    nextPage: function() {
        var page = 2;
        if (typeof h.pg !== 'undefined') page = parseInt(h.pg) + 1;
        this.setParam('pg', page);
    },
    prevPage: function() {
        var page = 1;
        if (typeof h.pg !== 'undefined') page = parseInt(h.pg) - 1;
        this.setParam('pg', page);
    },
    lastPage: function() {
        this.setParam('pg', this.getMaxPage());
    },
    isLoading: function() {
        return $('#loadingScreen').is(":visible"); 
    },
    getMaxPage: function() {
        return Math.ceil(this.resultCount / this.pp);
    },
    removeElement: function(el) {
        $('#' + el).remove();
    },
    exportConfirm: function() {
        var exports = [];
        $('#exportPreview').find('.dataContainer .title').each(function() {
            if ($(this).find('input[type=checkbox]').is(':checked')) exports.push($(this).attr('data-id'));
        });
        if (exports.length) exportData(true, exports);
        else return false;
    },
    viewFMT: function(fmt) {
        var args = ['fmt=' + fmt];
        for (var p in h) {
            args.push('data[' + p + ']=' + h[p]);
        }
        window.open('/?' + args.join('&'));
    }
}

function trimHTML(html) {
    return html.replace(/\>\s+\</g,'><');
}

function parseHash() {
    var hashPairs = location.hash.substring(2).split('&');
    var hashObject = {};
    for (var pair in hashPairs) {
        var parts = hashPairs[pair].split('=');
        if (parts[0] !== '') {
            hashObject[parts[0]] = parts[1];
        }
    }
    return hashObject;
}

function postMessages(messages, status, request, proper, clearFirst) {
    if (clearFirst) vuemsg.messages = [];
    for (var message in messages) {
        messages[message].status = status;
        messages[message].request = request;
        messages[message].proper = proper;
        var d = new Date();
        messages[message].timestamp = d.toLocaleDateString() + ' ' + d.toLocaleTimeString();
        vuemsg.messages.push(messages[message]);
    }
}

function pollTables() {
    if (typeof tables !== 'undefined') {
        for (var table in tables) {
            if (typeof vueobj[tables[table]] === 'undefined') vueobj[table] = {}
            pollData(false, table, tables[table]);
        }
    }
}

function loginAction(direction) {
    var parms = {};
    if (direction === 'out') {
        parms.action = 'logout';
        doLogin(parms);
    } else if (direction === 'in' && !$('#loginForm').length) {
        parms.action = 'login';
        $.get({
            url: '/html/login.html',
            cache: false
        }).done(function(html) {
            if (!$('#loginForm').length) {
                $('#container').append(trimHTML(html));
                $('#loadingScreen').hide();
                $('#loginForm').find('.cancel').on('click', function() {
                    $('#loginForm').remove();
                });
                $('#loginForm').find('.submit').on('click', function() {
                    $('#loginForm').find('input').each(function() {
                        parms[$(this).attr('id')] = $(this).val();
                    });
                    doLogin(parms);
                });
                $('#usr').focus();
            }
        });
    }
}

function doLogin(parms) {
    ajax = $.ajax({
        url: '/',
        type: 'POST',
        dataType: 'json',
        data: parms
    }).always(function(data) {
        if (typeof data.responseJSON !== 'undefined') data = data.responseJSON;
        if (data.status === 200) document.location.reload();

        if (data.user[loginConfig.uidField] === loginConfig.defaultUID) {
            $('#login')
                .html('<i class="fas fa-sign-in-alt">')
                .off('click')
                .on('click', function() { loginAction('in'); });
        } else {
            $('#login')
                .html('<i class="fas fa-sign-out-alt"></i>')
                .off('click')
                .on('click', function() { loginAction('out'); });
            $('#container').html('');
        }

        pollTables();
        $(window).trigger('hashchange');

        postMessages(data.messages, data.status, data.request, data.proper, true);
    });
}

function buildHash(add, remove) {
    clearTimeout(poll);
    if (remove) {
        if (remove === '*ALL') {
            h = {};
        } else {
            for (var keyr in remove) {
                if (typeof h[remove[keyr]] !== 'undefined') {
                    delete h[remove[keyr]];
                }
                if (typeof $('#' + remove[keyr]).length && $('#' + remove[keyr]).attr('type') !== 'checkbox') $('#' + remove[keyr]).val('');
            }
        }
    }
    if (add) {
        for (var keya in add) {
            if (typeof add[keya] !== 'undefined' && add[keya] !== '') h[keya] = add[keya];
            else if (add[keya] === '') delete h[keya];
        }
    }
    return Object.entries(h).reduce(function (total, pair) {
        const [key, value] = pair;
        return total + '&' + (key + '=' + value);
    }, '#!');
}

function handleHash() {
    if (!$.isEmptyObject(h)) {
        $('.viewOverlay').remove();
        if (!$('#container').find('#' + h.view).length) {
            $.get({
                url: '/vueelements/' + h.view + '.html',
                cache: false
            }).done(function(html) {
                $('#container').html(trimHTML(html));
                pollData(true);
                vueobj = new Vue({
                    el: '#container',
                    data: vueData,
                    methods: vueMethods
                });
                pollTables();
            });
        } else {
            pollData(true);
        }
    } else {
        $('#container').html('');
        $('#emptyRequest').show();
    }
    window.scrollTo(0, 0);
}

function pollCallback(zData, genView) {
    if (genView) {
        $('#exportPreview').hide();
        $('#emptyRequest').hide();
        $('#loadingScreen').hide();
    }
    if (zData.statusText === 'abort') return false;
    if (typeof zData.responseJSON !== 'undefined') data = zData.responseJSON;
    else data = zData;
    if (typeof loginConfig !== 'undefined') loginConfig = data.loginConfig;
    vueobj.user = data.user;

    postMessages(data.messages, data.status, data.request, data.proper, false);

    if (data.user[loginConfig.uidField] === loginConfig.defaultUID) {
        $('#login')
            .html('<i class="fas fa-sign-in-alt">')
            .off('click')
            .on('click', function() { loginAction('in'); });
        if (loginConfig.requireLogin) {
            loginAction('in');
        }
    } else {
        $('#login')
            .html('<i class="fas fa-sign-out-alt"></i>')
            .off('click')
            .on('click', function() { loginAction('out'); });
    }
}

function pollSuccess(data, key, genView) {
    vueobj[key] = data.results;
    vueobj.proper = data.proper;
    if (typeof data.resultCount !== 'undefined') vueobj.resultCount = data.resultCount;
    if (typeof data.pp !== 'undefined') vueobj.pp = data.pp;

    if (genView) $('#' + key).show();

    $('.filterBar').find('input, select').each(function() {
        var k = $(this).attr('id');
        if (typeof h[k] !== 'undefined' && $(this).attr('type') === 'checkbox') {
            if (h[k] === 'on') $(this).prop('checked', true);
            else $(this).prop('checked', false);
        } else if (typeof h[k] !== 'undefined') {
            $(this).val(h[k]);
        } else if ($(this)[0].tagName === 'SELECT') {
            $(this).find('option').each(function() {
                if ($(this).attr('selected')) $(this).parent().val($(this).val());
            });
        } else {
            $(this).val('');
        }
    });

    if (typeof data.poll !== 'undefined') {
        var filters = null;
        if (typeof tables !== 'undefined' && typeof tables[key] !== 'undefined') {
            filters = tables[key];
        }
        poll[key] = setTimeout(function() { pollData(genView, key, filters) }, data.poll);
    }
}

function pollFail(key, genView) {
    vueobj[key] = {};
}

function pollData(genView, table, filters) {
    if (typeof table === 'undefined') {
        table = h.view;
    }

    if (typeof filters === 'undefined' || filters === null) filters = h;
    else filters.view = table;

    if (typeof ajax[table] !== 'undefined' && ajax[table] !== null) {
        ajax[table].abort();
    }

    ajax[table] = $.ajax({
        url: '/',
        type: 'POST',
        dataType: 'json',
        data: { data: filters },
        beforeSend: function() {
            if (typeof h.view === 'undefined' || h.view === '') {
                $('#loadingScreen').hide();
                $('#emptyRequest').show();
            } else if (h.view === table && $('#loadingScreen').html() === '') {
                $.get({
                    url: '/html/loading.html',
                    cache: false
                }).done(function(html) {
                    $('#loadingScreen').html(html)
                    if (genView) {
                        $('#emptyRequest').hide();
                        $('#loadingScreen').show();
                    }
                });
            } else {
                if (genView) {
                    $('#emptyRequest').hide();
                    $('#loadingScreen').show();
                }
            }
        }
    }).always(function(data) {
        pollCallback(data, genView);
    }).done(function(data) {
        pollSuccess(data, table, genView);
    }).fail(function(data) {
        if (data.statusText !== 'abort') pollFail(table, genView);
    });
}

function exportData(confirm, exportList) {
    if ($('#exportPreview').length) {
        doExport(confirm, exportList);
    } else {
        $.get({
            url: '/vueelements/' + h.view + '_exp.html',
            cache: false
        }).done(function(html) {
            $('#container').append(trimHTML(html));
            doExport(confirm, exportList);
        });
    }
}

function doExport(confirm, exportList) {
    var postData = { data: h };
    postData.data.confirm = confirm ? 1: 0;
    if (confirm) {
        postData.data.exportList = exportList;
    }
    postData.action = 'export';

    $.ajax({
        url: '/',
        type: 'POST',
        dataType: 'json',
        data: postData
    }).always(function(zData) {
        if (zData.statusText === 'abort') return false;
              if (typeof zData.responseJSON !== 'undefined') data = zData.responseJSON;
              else data = zData;
              postMessages(data.messages, data.status, data.request, data.proper, false);
    }).done(function(data) {
        console.log(data);

        var vueexp = new Vue({
            el: '#exportPreview',
            data: { exportPreview: {}, proper: '' },
            methods: vueMethods
        });

        $('#loadingScreen').hide();
        
        vueobj[data.request] = data.results;
        if (data.exports.length) {
            vueexp.exportPreview = data.exports;
            vueexp.proper = data.proper;
        } else {
            //TODO: error - 0 results, need response from export write
            console.log('exports read failed');
        }
    }).fail(function(data) {
        console.log(data);
    });
}

$(document).on('change input', '.filterBar input, .filterBar select', function() {
    var el = $(this);
    setTimeout(function() { triggerFilter(el); }, 1000);
});

function triggerFilter(el) {
    var filter = {};
    var remove = [];
    if (typeof $(this).attr('data-remove') !== 'undefined') remove = el.attr('data-remove').split(',');
    
    switch (el.attr('type')) {
        case 'checkbox':
            if (el.is(':checked')) {
                filter[el.attr('name')] = el.val();
            } else {
                remove.push(el.attr('name'));
            }
            break;
        default:
            filter[el.attr('name')] = el.val();
            break;
    }
    window.location = buildHash(filter, remove);
}

$(document).on('click', '.viewOverlay', function() {
    window.location = buildHash(null, ['id']);
});

$(document).on('click', '.straightHash', function () {
    var hashes = {};
    for (var i in $(this).data()) {
        hashes[i] = $(this).attr('data-' + i);
    }
    window.location = buildHash(hashes, '*ALL');
});

$(document).on('click', '.export', function() {
    exportData(false, null);
});

$(window).on('hashchange', function() {
    h = parseHash();
    handleHash();
});

$(document).ready(function() {
    for (var table in tables) {
        vueData[table] = {};
    }

    pollTables();

    $('#nav').append('<span id="login"></span>');

    vuemsg = new Vue({
        el: '#notificationContainer',
        data: {
            messages: []
        },
        methods: {
            h: function() {
                if (typeof h.view === 'undefined') return '';
                else return h.view;
            },
            toggleMessages: function() {
                $('#notificationContainer > div.notification').toggleClass('hideByTransform');
            },
            closeMessage: function(idx) {
                Vue.delete(this.messages, idx);
            }
        }
    });

    $(window).trigger('hashchange');
});
