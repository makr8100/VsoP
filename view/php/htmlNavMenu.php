<?php

/**
 * htmlNavMenu.php - recurses navigation tree structure and creates HTML for nav bar
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-06-24
 * @package      VsoP
 * @name         htmlNavMenu.php
 * @since        2019-06-24
 * @version      0.11
 */

function recurseMenu($menu) {
    $navHTML = '<ul>';
    if (is_array($menu)) {
        foreach($menu as $link) {
            if (isset($link['children'])) {
                $navHTML .= '<li>' . $link['text'];
                $navHTML .= recurseMenu($link['children']);
                $navHTML .= '</li>';
            } else {
                $navHTML .= "<li><a class='link' href='{$link['uri']}'>{$link['text']}</a></li>";
            }       
        }
    }
    $navHTML .= '</ul>';

    return $navHTML;
}

echo '<div id="nav">' . recurseMenu($config['navigation']) . '</div>';
