// The "Before It Starts" help's screenshots, for the admin and the community event forms.
import relative from '../images/start-display-relative.png';
import countdown from '../images/start-display-countdown.png';
import relativeDark from '../images/start-display-relative-dark.png';
import countdownDark from '../images/start-display-countdown-dark.png';

const EXAMPLES = { relative, countdown, relative_dark: relativeDark, countdown_dark: countdownDark };

// The dark pair while the page is dark; read on each render, so the dialog follows a theme switch.
export function startDisplayExample(value) {
    return EXAMPLES[document.documentElement.classList.contains('dark') ? `${value}_dark` : value];
}
