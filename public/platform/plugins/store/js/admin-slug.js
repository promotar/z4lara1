(() => {
    'use strict';

    const characters = {
        '\u0621': 'a',
        '\u0622': 'a',
        '\u0623': 'a',
        '\u0624': 'w',
        '\u0625': 'i',
        '\u0626': 'y',
        '\u0627': 'a',
        '\u0628': 'b',
        '\u0629': 'a',
        '\u062a': 't',
        '\u062b': 'th',
        '\u062c': 'j',
        '\u062d': 'h',
        '\u062e': 'kh',
        '\u062f': 'd',
        '\u0630': 'dh',
        '\u0631': 'r',
        '\u0632': 'z',
        '\u0633': 's',
        '\u0634': 'sh',
        '\u0635': 's',
        '\u0636': 'd',
        '\u0637': 't',
        '\u0638': 'z',
        '\u0639': 'a',
        '\u063a': 'gh',
        '\u0641': 'f',
        '\u0642': 'q',
        '\u0643': 'k',
        '\u0644': 'l',
        '\u0645': 'm',
        '\u0646': 'n',
        '\u0647': 'h',
        '\u0648': 'w',
        '\u0649': 'a',
        '\u064a': 'y',
        '\u067e': 'p',
        '\u0686': 'ch',
        '\u06a4': 'v',
        '\u06af': 'g',
        '\u06a9': 'k',
        '\u06cc': 'y',
        '\u0660': '0',
        '\u0661': '1',
        '\u0662': '2',
        '\u0663': '3',
        '\u0664': '4',
        '\u0665': '5',
        '\u0666': '6',
        '\u0667': '7',
        '\u0668': '8',
        '\u0669': '9',
        '\u06f0': '0',
        '\u06f1': '1',
        '\u06f2': '2',
        '\u06f3': '3',
        '\u06f4': '4',
        '\u06f5': '5',
        '\u06f6': '6',
        '\u06f7': '7',
        '\u06f8': '8',
        '\u06f9': '9'
    };

    const slugify = (value) => {
        const normalized = String(value || '')
            .toLowerCase()
            .normalize('NFKD')
            .replace(/(^|[\s_-])\u0627\u0644(?=[\u0621-\u064a])/g, '$1al-');

        return Array.from(normalized)
            .map((character) => characters[character] ?? character)
            .join('')
            .replace(/[\u0300-\u036f\u064b-\u065f\u0670\u06d6-\u06ed\u0640]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .replace(/-{2,}/g, '-');
    };

    window.Z4StoreSlugify = slugify;

    document.querySelectorAll('[data-z4-store-slug-form]').forEach((form) => {
        const source = form.querySelector('[data-slug-source]');
        const target = form.querySelector('[data-slug-target]');
        if (!source || !target) return;

        let generatedSlug = slugify(source.value);
        let autoSync = target.value.trim() === '' || target.value.trim() === generatedSlug;
        let applyingGeneratedValue = false;

        const syncSlug = () => {
            if (!autoSync) return;

            generatedSlug = slugify(source.value);
            applyingGeneratedValue = true;
            target.value = generatedSlug;
            target.dispatchEvent(new Event('input', { bubbles: true }));
            applyingGeneratedValue = false;
        };

        source.addEventListener('input', syncSlug);
        target.addEventListener('input', () => {
            if (applyingGeneratedValue) return;

            const value = target.value.trim();
            autoSync = value === '' || value === generatedSlug;
            if (value === '') syncSlug();
        });

        if (target.value.trim() === '' && source.value.trim() !== '') {
            syncSlug();
        }
    });
})();
