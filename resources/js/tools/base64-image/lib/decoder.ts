export interface DecodedImageResult {
    valid: boolean;
    mimeType?: string;
    extension?: string;
    blob?: Blob;
    objectUrl?: string;
    sizeBytes?: number;
    error?: string;
}

export function parseBase64Image(rawInput: string): DecodedImageResult {
    const trimmed = rawInput.trim();
    if (!trimmed) {
        return { valid: false, error: 'empty_input' };
    }

    let mimeType = 'image/png';
    let base64Data = trimmed;

    // Reconhece Data URI data:image/...;base64,...
    const dataUriMatch = trimmed.match(/^data:([a-zA-Z0-9/+-]+);base64,(.+)$/s);
    if (dataUriMatch && dataUriMatch[1] && dataUriMatch[2]) {
        mimeType = dataUriMatch[1];
        base64Data = dataUriMatch[2].replace(/\s+/g, '');
    } else {
        base64Data = trimmed.replace(/\s+/g, '');

        // Inferencia de MIME basica por magic bytes comuns em base64
        if (base64Data.startsWith('/9j/')) {
            mimeType = 'image/jpeg';
        } else if (base64Data.startsWith('iVBORw0KGgo')) {
            mimeType = 'image/png';
        } else if (base64Data.startsWith('R0lGOD')) {
            mimeType = 'image/gif';
        } else if (base64Data.startsWith('UklGR')) {
            mimeType = 'image/webp';
        } else if (base64Data.startsWith('PHN2Zw') || base64Data.startsWith('PD94b')) {
            mimeType = 'image/svg+xml';
        }
    }

    try {
        const byteCharacters = atob(base64Data);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        const blob = new Blob([byteArray], { type: mimeType });
        const objectUrl = URL.createObjectURL(blob);

        let extension = 'png';
        if (mimeType.includes('jpeg') || mimeType.includes('jpg')) {
            extension = 'jpg';
        } else if (mimeType.includes('webp')) {
            extension = 'webp';
        } else if (mimeType.includes('gif')) {
            extension = 'gif';
        } else if (mimeType.includes('svg')) {
            extension = 'svg';
        }

        return {
            valid: true,
            mimeType,
            extension,
            blob,
            objectUrl,
            sizeBytes: blob.size,
        };
    } catch {
        return { valid: false, error: 'invalid_base64' };
    }
}
