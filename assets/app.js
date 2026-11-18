import './stimulus_bootstrap.js';
/*
 * Point d'entrée JavaScript, inclus sur chaque page via importmap('app')
 * (templates/base.html.twig). Les feuilles de style de la charte 2026 sont
 * importées ici, dans l'ordre : tokens (couleurs, thèmes clair/sombre),
 * base, ossature (header, footer), accueil, contenus, pages de service.
 */
import './styles/tokens.css';
import './styles/base.css';
import './styles/layout.css';
import './styles/home.css';
import './styles/content.css';
import './styles/pages.css';
