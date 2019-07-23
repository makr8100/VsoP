/**
 * hash.js - parse HTML hash and convert to JavaScript object, then send AJAX request
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-06-24
 * @package      VsoP
 * @name         hash.js
 * @since        2019-06-24
 * @version      0.11
 */

var poll = null;

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

var h = parseHash();

function buildHash(add, remove) {
    clearTimeout(poll);
    if (remove) {
        for (var keyr in remove) {
            if (typeof h[keyr] !== 'undefined') delete h[keyr];
        }
    }
    if (add) {
        for (var keya in add) {
            h[keya] = add[keya];
        }
    }
//     delete h[''];
    return Object.entries(h).reduce(function (total, pair) {
        const [key, value] = pair;
        return total + '&' + (key + '=' + value);
    }, '#!');
}

function handleHash() {
    if (!$.isEmptyObject(h)) {
        var ajax = $.ajax({
            url: h.view,
            type: 'POST',
            dataType: 'json',
            data: {
                data: h
            },
            beforeSend: function() {
                $('.view').hide();
            }
        }).done(function(data) {
            console.log(data);
            if (data.status == 200) {
                if (h.action == 'view' && typeof h.id === 'undefined') {
                    vueobj[h.view] = data.results;
                    $('#' + h.view).show();
                    if (typeof data.poll !== 'undefined') poll = setTimeout(pollData(), data.poll);
                } else if (h.action == 'view') {
                    // TODO: html for detail
                } else if (h.action == 'new') {
                    // TODO: forms for new
                }
            } else {
                //fail
                console.log(data);
            }
        }).fail(function(data) {
            //fail
            console.log(data);
        });
    }
}

function pollData() {
    var ajax = $.ajax({
            url: h.view,
            type: 'POST',
            dataType: 'json',
            data: {
                data: h
            }
        }).done(function(data) {
            console.log(data);
            if (data.status == 200) {
                if (h.action == 'view' && typeof h.id === 'undefined') {
                    vueobj[h.view] = data.results;
                    $('#' + h.view).show();
                    if (typeof data.poll !== 'undefined') {
                        poll = setTimeout(function() { pollData() }, data.poll);
                    }
                    console.log(data.poll);
                } else if (h.action == 'view') {
                    // TODO: html for detail
                }
            } else {
                //fail
                console.log(data);
            }
        }).fail(function(data) {
            //fail
            console.log(data);
        });
}

$(window).on('hashchange', function() {
    h = parseHash();
    handleHash();
});
