import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

// assets/package.json and assets/controllers.json only exist so that tooling reading the
// Symfony UX layout (Symfony Language Tools) knows the controllers registered at runtime by
// public/ea-extensions.js. Nothing enforces that the three files stay in sync, except this test.
const bundleDir = resolve(__dirname, '../..');
const read = (path) => readFileSync(resolve(bundleDir, path), 'utf8');

const runtimeControllers = () => {
    const loaders = {};
    for (const [, name, path] of read('public/ea-extensions.js').matchAll(/'([a-z-]+)': \(\) => import\('\.\/(controllers\/[a-z_]+\.js)'\)/g)) {
        loaders[name] = `public/${path}`;
    }

    return loaders;
};

describe('Stimulus manifest', () => {
    const packageJson = JSON.parse(read('assets/package.json'));
    const controllersJson = JSON.parse(read('assets/controllers.json'));
    const declared = packageJson.symfony.controllers;

    it('is declared under the bundle Composer package name', () => {
        const composerName = JSON.parse(read('composer.json')).name;

        expect(packageJson.name).toBe(`@${composerName}`);
        expect(Object.keys(controllersJson.controllers)).toEqual([packageJson.name]);
    });

    it('declares every controller registered by the entrypoint, under the same name and source', () => {
        const manifest = Object.fromEntries(
            Object.values(declared).map(({ name, main }) => [name, resolve(bundleDir, 'assets', main)]),
        );
        const runtime = Object.fromEntries(
            Object.entries(runtimeControllers()).map(([name, path]) => [name, resolve(bundleDir, path)]),
        );

        expect(Object.keys(runtime).length).toBeGreaterThan(0);
        expect(manifest).toEqual(runtime);
        Object.values(manifest).forEach((path) => expect(existsSync(path)).toBe(true));
    });

    it('enables every declared controller in the bundle controllers.json', () => {
        const enabled = controllersJson.controllers[packageJson.name];

        expect(Object.keys(enabled).sort()).toEqual(Object.keys(declared).sort());
        Object.values(enabled).forEach((config) => expect(config.enabled).toBe(true));
    });
});
