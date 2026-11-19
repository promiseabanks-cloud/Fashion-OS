import type { FashionOSConfig } from './types';

declare global {
  interface Window {
    FashionOS?: FashionOSConfig;
  }
}

export {};
