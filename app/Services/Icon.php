<?php

namespace FluentEvent\App\Services;

/**
 * SVG icons for PHP-rendered markup.
 *
 * Each icon is a plain constant holding a complete <svg> string, so a view
 * asks for one by name and echoes it.
 */
class Icon
{
    /**
     * Sun — shown in dark mode, click to return to light.
     */
    const SUN = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
        . '<path d="M14.1663 10.0007C14.1663 12.3018 12.3009 14.1673 9.99967 14.1673C7.69849 14.1673 5.83301 12.3018 5.83301 10.0007C5.83301 7.69946 7.69849 5.83398 9.99967 5.83398C12.3009 5.83398 14.1663 7.69946 14.1663 10.0007Z" stroke="currentColor" stroke-width="1.5"/>'
        . '<path d="M9.99984 1.66699V2.91699M9.99984 17.0837V18.3337M15.8922 15.8931L15.0083 15.0092M4.99089 4.99137L4.107 4.10749M18.3332 10.0003H17.0832M2.9165 10.0003H1.6665M15.8926 4.10758L15.0087 4.99147M4.99129 15.0093L4.10741 15.8932" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>'
        . '</svg>';

    /**
     * Moon — shown in light mode, click to switch to dark.
     */
    const MOON = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
        . '<path d="M17.9163 11.7317C16.9166 12.2654 15.7748 12.5681 14.5623 12.5681C10.6239 12.5681 7.43128 9.37543 7.43128 5.43705C7.43128 4.22456 7.73388 3.08274 8.2677 2.08301C4.72272 2.91382 2.08301 6.09562 2.08301 9.89393C2.08301 14.3246 5.67476 17.9163 10.1054 17.9163C13.9038 17.9163 17.0855 15.2767 17.9163 11.7317Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';

    /**
     * Gear — the Settings topbar entry.
     */
    const SETTING = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 1024 1024" fill="none" aria-hidden="true">'
        . '<path fill="currentColor" d="M600.704 64a32 32 0 0 1 30.464 22.208l35.2 109.376c14.784 7.232 28.928 15.36 42.432 24.512l112.384-24.192a32 32 0 0 1 34.432 15.36L944.32 364.8a32 32 0 0 1-4.032 37.504l-77.12 85.12a357.12 357.12 0 0 1 0 49.024l77.12 85.248a32 32 0 0 1 4.032 37.504l-88.704 153.6a32 32 0 0 1-34.432 15.296L708.8 803.904c-13.44 9.088-27.648 17.28-42.368 24.512l-35.264 109.376A32 32 0 0 1 600.704 960H423.296a32 32 0 0 1-30.464-22.208L357.696 828.48a351.616 351.616 0 0 1-42.56-24.64l-112.32 24.256a32 32 0 0 1-34.432-15.36L79.68 659.2a32 32 0 0 1 4.032-37.504l77.12-85.248a357.12 357.12 0 0 1 0-48.896l-77.12-85.248A32 32 0 0 1 79.68 364.8l88.704-153.6a32 32 0 0 1 34.432-15.296l112.32 24.256c13.568-9.152 27.776-17.408 42.56-24.64l35.2-109.312A32 32 0 0 1 423.232 64H600.64zm-23.424 64H446.72l-36.352 113.088-24.512 11.968a294.113 294.113 0 0 0-34.816 20.096l-22.656 15.36-116.224-25.088-65.28 113.152 79.68 88.192-1.92 27.136a293.12 293.12 0 0 0 0 40.192l1.92 27.136-79.808 88.192 65.344 113.152 116.224-25.024 22.656 15.296a294.113 294.113 0 0 0 34.816 20.096l24.512 11.968L446.72 896h130.688l36.48-113.152 24.448-11.904a288.282 288.282 0 0 0 34.752-20.096l22.592-15.296 116.288 25.024 65.28-113.152-79.744-88.192 1.92-27.136a293.12 293.12 0 0 0 0-40.256l-1.92-27.136 79.808-88.128-65.344-113.152-116.288 24.96-22.592-15.232a287.616 287.616 0 0 0-34.752-20.096l-24.448-11.904L577.344 128zM512 320a192 192 0 1 1 0 384 192 192 0 0 1 0-384zm0 64a128 128 0 1 0 0 256 128 128 0 0 0 0-256z"/>'
        . '</svg>';

    /**
     * Hamburger
     */
    const MENU = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">'
        . '<path fill-rule="evenodd" clip-rule="evenodd" d="M3 4.5C3 3.94772 3.44772 3.5 4 3.5L20 3.5C20.5523 3.5 21 3.94772 21 4.5C21 5.05229 20.5523 5.5 20 5.5L4 5.5C3.44772 5.5 3 5.05228 3 4.5Z" fill="currentColor"/>'
        . '<path fill-rule="evenodd" clip-rule="evenodd" d="M3 9.5C3 8.94772 3.44772 8.5 4 8.5L20 8.5C20.5523 8.5 21 8.94772 21 9.5C21 10.0523 20.5523 10.5 20 10.5L4 10.5C3.44772 10.5 3 10.0523 3 9.5Z" fill="currentColor"/>'
        . '<path fill-rule="evenodd" clip-rule="evenodd" d="M3 14.5C3 13.9477 3.44772 13.5 4 13.5L20 13.5C20.5523 13.5 21 13.9477 21 14.5C21 15.0523 20.5523 15.5 20 15.5L4 15.5C3.44772 15.5 3 15.0523 3 14.5Z" fill="currentColor"/>'
        . '<path fill-rule="evenodd" clip-rule="evenodd" d="M3 19.5C3 18.9477 3.44772 18.5 4 18.5L20 18.5C20.5523 18.5 21 18.9477 21 19.5C21 20.0523 20.5523 20.5 20 20.5L4 20.5C3.44772 20.5 3 20.0523 3 19.5Z" fill="currentColor"/>'
        . '</svg>';

    /**
     * Calendar
     */
    const CALENDAR = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
        . '<path d="M6.66667 1.66699V4.16699M13.3333 1.66699V4.16699M2.5 7.50033H17.5M4.16667 3.33366H15.8333C16.7538 3.33366 17.5 4.07985 17.5 5.00033V16.667C17.5 17.5875 16.7538 18.3337 15.8333 18.3337H4.16667C3.24619 18.3337 2.5 17.5875 2.5 16.667V5.00033C2.5 4.07985 3.24619 3.33366 4.16667 3.33366Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';

    /**
     * Four squares — the Cards view.
     */
    const GRID = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
        . '<path d="M3.33 3.33H8.33V8.33H3.33V3.33ZM11.67 3.33H16.67V8.33H11.67V3.33ZM3.33 11.67H8.33V16.67H3.33V11.67ZM11.67 11.67H16.67V16.67H11.67V11.67Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>'
        . '</svg>';

    /**
     * Bulleted list — the Timeline view.
     */
    const TIMELINE = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
        . '<path d="M7.5 5H16.67M7.5 10H16.67M7.5 15H16.67M3.75 5H3.76M3.75 10H3.76M3.75 15H3.76" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';

    /**
     * Video camera — an online event.
     */
    const VIDEO = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
        . '<path d="M13.33 8.33L17.5 5.83V14.17L13.33 11.67M4.17 15H11.67C12.59 15 13.33 14.25 13.33 13.33V6.67C13.33 5.75 12.59 5 11.67 5H4.17C3.25 5 2.5 5.75 2.5 6.67V13.33C2.5 14.25 3.25 15 4.17 15Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';

    /**
     * Map pin — an in-person event.
     */
    const MAP_PIN = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
        . '<path d="M16.67 8.33C16.67 13.33 10 17.5 10 17.5C10 17.5 3.33 13.33 3.33 8.33C3.33 4.65 6.32 1.67 10 1.67C13.68 1.67 16.67 4.65 16.67 8.33Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<path d="M10 10.83C11.38 10.83 12.5 9.71 12.5 8.33C12.5 6.95 11.38 5.83 10 5.83C8.62 5.83 7.5 6.95 7.5 8.33C7.5 9.71 8.62 10.83 10 10.83Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';

    /**
     * Arrow left — back links.
     */
    const ARROW_LEFT = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
        . '<path d="M15.83 10H4.17M4.17 10L9.17 15M4.17 10L9.17 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';

    const CHEVRON_LEFT = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
        . '<path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';

    const CHEVRON_RIGHT = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">'
        . '<path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';
}
