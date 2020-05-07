/**
 * hash.js - parse HTML hash and convert to JavaScript object, then send AJAX request
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2020-01-15
 * @package      VsoP
 * @name         hash.js
 * @since        2019-06-24
 * @version      0.15
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
        return typeof h[check] !== 'undefined' ? h[check] : (check === 'pg' ? 1 : false);
    },
    back: function(param) {
        window.history.back();
        if (typeof param !== 'undefined') window.onpopstate = function() {
            if (typeof h.action !== 'undefined') delete h.action;
            vueMethods.clearParam(param, true);
        };
    },
    edit: function(view, key, param) {
        console.log(arguments);
        var params = {
            view: view,
            action: 'edit'
        };
        params[key] = param;
        window.location = buildHash(params);
    },
    add: function(view, key, param, child, ckey, defOpts) {
        console.log(arguments);
        if ($('#' + view + ' .modalOnly .toggleCollapse[data-collapse-id="new"]').length) {
            postMessages([{ type: 'warn', message: 'ONE AT A TIME BUDDY!' }], null, null, 'Oops!', false);
            return false;
        }
        var newObj = {}
        for (var i in this[view][0][child][0]) {
            if (i === ckey) newObj[i] = 'new';
            else newObj[i] = null;
        }
        newObj[defOpts] = this[defOpts];
        this[view][0][child].unshift(newObj);
        //TODO: just expand, don't trigger click - clicks all and is wonky
        $('#' + view + ' .modalOnly .toggleCollapse:first').trigger('click');
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
        if ($('#' + param).attr('type') === 'checkbox') {
            $('#' + param).prop('checked', false).trigger('input');
        } else {
            $('#' + param).val('').trigger('input');
        }

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
    if (typeof data.loginConfig === 'undefined') return false;
    loginConfig = data.loginConfig;
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
        if ($(this).attr('type') === 'checkbox') {
            if (typeof h[k] === 'undefined' || h[k] !== 'on') $(this).prop('checked', false);
            else $(this).prop('checked', true);
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
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(function() { triggerFilter(el); }, 1000);
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

$(document).on('click', '#filterToggle', function() {
    if ($('.filterBar').is(':visible')) $('.filterBar').hide();
    else $('.filterBar').show();
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

$(document).on('click', '.toggleCollapse', function() {
    $(this).parent().find('.collapse[data-collapse-id="' + $(this).attr('data-collapse-id') + '"]').toggleClass('collapsed');
    $(this).find('.close > i').attr('class',
        $(this).parent().find('.collapse[data-collapse-id="' + $(this).attr('data-collapse-id') + '"]').hasClass('collapsed') ? 'fas fa-chevron-down' : 'fas fa-chevron-up');
});

$(document).on('click','.changeState', function() {
    var el = $(this);
    var state = vueobj[h.view][el.attr('data-id')][el.attr('data-set')][el.attr('data-idx')][el.attr('data-line')][el.attr('data-lidx')][el.attr('data-state')];
    if (typeof state !== 'number') state = 0;
    if (state >= el.attr('data-maxstate')) state = -1;
    state ++;
    console.log(state);
    vueobj[h.view][el.attr('data-id')][el.attr('data-set')][el.attr('data-idx')][el.attr('data-line')][el.attr('data-lidx')][el.attr('data-state')] = state;
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

    $('#nav').append('<span id="login"></span><span id="filterToggle"><i class="fas fa-filter"></i></span>');

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

$(document).on('keydown', function(e) {
    if ([192].indexOf(e.which) > -1) {
        e.preventDefault();
    }
});

$(document).on('keyup', function(e) {
    switch (e.which) {
        case 13: // enter
            e.preventDefault();
            $('.enterKey').trigger('click');
            break;
        case 38: // up
            if ($('#cmd').is(':focus')) {
                e.preventDefault();
                var hist = {};
                var mode = $('#cmd').attr('data-mode');
                if (typeof Storage === 'function') {
                    if (typeof localStorage.cmdHist !== 'undefined' && localStorage.cmdHist !== '') hist = JSON.parse(localStorage.getItem('cmdHist'));
                } else {
                }
                if (typeof hist[mode] === 'undefined') {
                    console.log('no ' + mode + 'history');
                    return false;
                }
                var newID;
                if (typeof $('#cmd').attr('data-id') === 'undefined') {
                    newID = hist[mode].length - 1;
                } else {
                    newID = $('#cmd').attr('data-id') - 1;
                }
                if (newID < 0) newID = hist[mode].length - 1;
                $('#cmd').attr('data-id', newID).val(hist[mode][newID]);
            }
            break;
        case 40: // down
            if ($('#cmd').is(':focus')) {
                e.preventDefault();
                var hist = {};
                var mode = $('#cmd').attr('data-mode');
                if (typeof Storage === 'function') {
                    if (typeof localStorage.cmdHist !== 'undefined' && localStorage.cmdHist !== '') hist = JSON.parse(localStorage.getItem('cmdHist'));
                } else {
                }
                if (typeof hist[mode] === 'undefined') {
                    console.log('no ' + mode + 'history');
                    return false;
                }
                var newID;
                if (typeof $('#cmd').attr('data-id') === 'undefined') {
                    newID = 0;
                } else {
                    newID = parseInt($('#cmd').attr('data-id')) + 1;
                }
                if (newID >= hist[mode].length) newID = 0;
                $('#cmd').attr('data-id', newID).val(hist[mode][newID]);
            }
            break;
        case 192: // `
            if (!$('#devBox').length) {
                //TODO: get permissions
                $.get({
                    url: '/html/devbox.html',
                    cache: false
                }).done(function(html) {
                    $('body').prepend(trimHTML(html));
                    $('#devBox').find('.submit').on('click', function() {
                        runcmd($('#cmd').val());
                        $('#cmd').val('');
                    });
                    $('#cmd').focus();
                });
            } else {
                e.preventDefault();
                if ($('#cmd').val().indexOf('sql ') !== 0) {
                    $('#devBox').toggleClass('hidden');
                }
                if (!$('#devBox').hasClass('hidden')) {
                    $('#cmd').focus();
                }
                if ($('#cmd').val() === '`') $('#cmd').val('');
            }
            break;
        default:
            console.log('No action defined for ' + e.which);
            break;
    }
});

function runcmd(cmd) {
    var mode = $('#cmd').attr('data-mode');
    if (cmd === 'clear') {
        $('#console').html('');
    } else if (cmd === 'clear history') {
        localStorage.setItem('cmdHist', '');
    } else if (['js','php','sql'].indexOf(cmd) > -1) {
        $('#cmd').attr('data-mode', cmd).val('');
    } else if (mode == 'js') {
        var result = eval(cmd);
        var output;
        switch (typeof result) {
            case 'object':
                output = JSON.stringify(result);
                break;
            default:
                output = result;
                break;
        }
        $('#console').append('<pre class="cmd">' + cmd + '</pre>').append('<pre>' + output + '</pre>').animate({ scrollTop: $('#console').prop("scrollHeight")}, 300);
        //TODO: localStorage store cmd history
    } else {
        ajax = $.ajax({
            url: '/',
            type: 'POST',
            dataType: 'json',
            data: parms
        }).always(function(data) {
            //TODO: ajax
        });
    }

    //TODO: detect error, prevent saving
    if (typeof Storage === 'function' && (['js','php','sql'].indexOf(cmd) !== false)) {
        var hist = {};
        if (typeof localStorage.cmdHist !== 'undefined' && localStorage.cmdHist !== '') hist = JSON.parse(localStorage.getItem('cmdHist'));
        if (typeof hist[mode] === 'undefined') hist[mode] = [];
        if (hist[mode].indexOf(cmd) > -1) {
            hist[mode].splice(hist[mode].indexOf(cmd));
        }
        hist[mode].push(cmd);
        localStorage.setItem('cmdHist', JSON.stringify(hist));
        $('#cmd').attr('data-id', hist[mode].length + 1);
    }
}
