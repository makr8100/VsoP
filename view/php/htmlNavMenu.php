<?php

/**
 * htmlNavMenu.php - recurses navigation tree structure and creates HTML for nav bar
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-12-13
 * @package      VsoP
 * @name         htmlNavMenu.php
 * @since        2019-06-24
 * @version      0.13
 * @license      MIT
 */

//TODO: vue for nav menu

function recurseMenu($menu, $buttons = false, $depth = 0) {
    global $sess;
    if ($buttons && empty($depth)) $navHTML = '<div>';
    else if ($buttons) $navHTML = '<div class="topBorder">';
    else $navHTML = '<ul>';
    if (is_array($menu)) {
        foreach($menu as $link) {
            if (!isset($link['authority']) || !empty($sess->user['authority'][$link['authority']])) {
                if (isset($link['children'])) {
                    $childHTML = recurseMenu($link['children'], $buttons, $depth + 1);
                }

                $icon = '';
                if (!empty($link['icon'])) $icon = "<i class='fas {$link['icon']}'></i>";

                if (isset($link['children'])) {
                    if ($buttons && !empty(strip_tags($childHTML))) $navHTML .= "<div><h3>{$icon}{$link['text']}</h3><div class='center'>{$childHTML}</div></div>";
                    else if (!empty(strip_tags($childHTML))) $navHTML .= "<li>{$icon}{$childHTML}{$link['text']}</li>";
                } else {
                    if ($buttons) $navHTML .= "<a class='button' href='{$link['uri']}'>{$icon}<div>{$link['text']}</div></a>";
                    else $navHTML .= "<li><a class='link' href='{$link['uri']}'>{$icon}{$link['text']}</a></li>";
                }
            }
        }
    }
    if ($buttons) $navHTML .= '</div>';
    else $navHTML .= '</ul>';

    return $navHTML;
}

echo '<div id="nav">' . recurseMenu($config['navigation']) . '</div>';
