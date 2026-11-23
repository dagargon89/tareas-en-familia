import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './styles/app.css';
import App from './App.tsx';

const raiz = document.getElementById('root');
if (!raiz) {
  throw new Error('No existe el elemento #root en index.html');
}

createRoot(raiz).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
