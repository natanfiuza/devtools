<?php

/*
|--------------------------------------------------------------------------
| Registro de tools (textos)
|--------------------------------------------------------------------------
|
| O `manifest.json` de cada tool guarda so identidade e ciclo de vida; todo
| texto visivel mora aqui, por convencao `tools.<slug>.<campo>` (o slug e o
| nome do diretorio). Os nomes das tools ficam em ingles em todos os
| idiomas: sao nomes de produto, como aparecem na SPEC e no DESIGN.
|
*/

return [

    'status' => [
        'soon' => 'Coming soon',
    ],

    /*
    | Textos da casca de qualquer tool (os paineis, a barra de acoes e os
    | estados). O que e de uma ferramenta so mora em `tools.<slug>.*`.
    */
    'ui' => [
        'input' => 'Input',
        'output' => 'Output',
        'resize' => 'Resize the panels',
        'drop' => 'Drop the file here',

        'actions' => [
            'open' => 'Open file',
            'example' => 'Example',
            'clear' => 'Clear',
            'copy' => 'Copy',
            'copied' => 'Copied',
            'copy_manual' => 'Select the text and copy it',
            'download' => 'Download',
        ],

        'states' => [
            'empty' => 'Paste, type or drop a file to start.',
            'parsing' => 'Processing...',
            'unavailable' => 'Tool unavailable.',
            'invalid' => 'Invalid input on line {line}, column {column}.',
            'invalid_no_position' => 'Invalid input.',
            'stale_output' => 'Output from the last valid input.',
            'too_large' => 'The text is {size} and goes past the {limit} limit: nothing will be processed.',
            'file_too_large' => 'The file is {size} and goes past the {limit} limit.',
            'file_type' => 'Unsupported format. Use a {formats} file.',
            'file_unreadable' => 'Could not read the file.',
            'drop_hint' => 'Drop to open the file.',
        ],

        'limits' => [
            'of' => 'of {limit}',
        ],

        'shortcuts' => [
            'title' => 'Shortcuts',
        ],
    ],

    /*
    | Interface shared between tools. The colour picker is used by more than
    | one tool, so its texts live here instead of `tools.<slug>.*`.
    */
    'shared' => [
        'color_picker' => [
            'color_input' => [
                'label' => 'Colour',
                'placeholder' => '#7c3aed, rgb(124 58 237), oklch(54% 0.24 293)…',
                'hint' => 'Accepts HEX, RGB, HSL, OKLCH, OKLab and CSS names. The colour is applied as you type once the text is valid.',
                'empty' => 'Type a colour to start.',
                'syntax' => 'That format could not be understood. Check the code and try again.',
                'range' => 'One of the values is outside the allowed range.',
                'unknown_name' => 'There is no CSS colour with that name.',
            ],

            'area' => [
                'label' => 'Saturation and brightness',
                'hint' => 'Arrow keys adjust 1 point; hold Shift for 10.',
            ],

            'hue' => [
                'label' => 'Hue',
            ],

            'alpha' => [
                'label' => 'Opacity',
            ],

            'preview' => [
                'eyedropper' => 'Screen eyedropper',
            ],

            'channels' => [
                'title' => 'Channels',
                'rgb' => 'RGB',
                'hsl' => 'HSL',
                'red' => 'Red',
                'green' => 'Green',
                'blue' => 'Blue',
                'hue' => 'Hue',
                'saturation' => 'Saturation',
                'lightness' => 'Lightness',
            ],

            'formats' => [
                'title' => 'Colour formats',
                'hex' => 'HEX',
                'hex8' => 'HEX8',
                'rgb' => 'RGB',
                'rgba' => 'RGBA',
                'hsl' => 'HSL',
                'hsla' => 'HSLA',
                'oklch' => 'OKLCH',
                'oklab' => 'OKLab',
                'name' => 'CSS name',
                'copy' => 'Copy {format}',
                'copied' => 'Copied: {value}',
                'name_hint' => 'The CSS name is the closest match; it may differ slightly from the code.',
            ],
        ],

        'library' => [
            'history' => [
                'title' => 'History',
                'description' => [
                    'account' => 'The colours you applied most recently, saved in your account and shared with the generator.',
                    'local' => 'The colours you applied most recently, only in this browser. Sign in to keep them in your account.',
                ],
                'clear' => 'Clear',
                'empty' => 'No colour applied yet.',
            ],

            'favorites' => [
                'title' => 'Favourites',
                'description' => [
                    'account' => 'Colours saved in your account, shared between the colour picker and the palette generator.',
                    'local' => 'Colours saved only in this browser.',
                ],
                'add' => 'Favourite current colour',
                'remove' => 'Remove from favourites',
                'clear' => 'Clear',
                'empty' => 'No favourites yet.',
            ],

            'import' => [
                'button' => 'Import browser history ({count})',
                'title' => 'Import browser history',
                'description' => 'Send {count} colour(s) saved in this browser to your account. The local list is cleared after the import and duplicates are ignored.',
                'confirm' => 'Import',
                'cancel' => 'Cancel',
                'busy' => 'Importing…',
                'success' => 'Imported {count} colour(s) into your account.',
                'error' => 'The import failed. Your local history was kept; try again.',
            ],

            'load_more' => 'Load more',
        ],

        'token_import' => [
            'button' => 'Import tokens',
            'applied' => 'Tokens applied.',
            'title' => 'Import design tokens',
            'description' => 'Read the tool’s native JSON, W3C DTCG (Tokens Studio or Figma Variables) or Style Dictionary. The file is read in your browser and never uploaded.',
            'choose' => 'Choose a JSON file',
            'hint' => 'Up to {size}. You can also drop the file here.',
            'reading' => 'Reading the file…',

            'file_errors' => [
                'file_too_large' => 'The file is over the {limit} limit.',
                'file_type' => 'Choose a .json file.',
                'file_unreadable' => 'Could not read the file. Try another one.',
            ],

            'diagnostics' => [
                'title' => 'Diagnostics',
                'errors' => 'Errors',
                'warnings' => 'Warnings',
                'hidden' => '{count} more diagnostic(s) not shown.',
                'path' => 'at {path}',
            ],

            'preview' => [
                'title' => 'Preview',
                'format' => 'Format',
                'kind' => 'Kind',
                'base' => 'Base color',
                'colors_label' => 'Colors',
                'colors' => '{count} colors',
                'roles' => 'Semantic roles included',
                'no_roles' => 'No complete semantic roles',
                'name' => 'Name in the file: {name}',
                'filename' => 'File: {name}',
                'apply' => 'Apply',
                'cancel' => 'Cancel',
                'replace' => 'Choose another file',
                'apply_hint' => 'The current palette only changes when you apply.',
            ],

            'formats' => [
                'native' => 'Tool JSON',
                'dtcg' => 'W3C DTCG',
                'style-dictionary' => 'Style Dictionary',
            ],

            'kinds' => [
                'scale' => '11-tone scale',
                'extracted' => 'Extracted palette',
            ],

            'codes' => [
                'empty_file' => 'The file is empty.',
                'too_large' => 'The file is over the {max} limit.',
                'invalid_json' => 'The JSON is not valid (line {line}, column {column}).',
                'root_not_object' => 'The file root must be a JSON object.',
                'unsupported_format' => 'No recognizable color tokens were found.',
                'ambiguous_format' => 'The file mixes more than one token style.',
                'unsupported_format_version' => 'The format version ({found}) is not supported; this tool reads version {expected}.',
                'too_deep' => 'The structure exceeds the maximum depth of {max}.',
                'too_many_tokens' => 'The file has more than {max} tokens.',
                'forbidden_key' => 'The key “{key}” is not allowed.',
                'mixed_token_styles' => 'Tokens in different styles appear in the same group.',
                'invalid_structure' => 'The palette structure is not recognizable.',
                'no_colors' => 'The file has no colors to import.',
                'ambiguous_palette' => 'More than one palette is possible; leave just one to import.',
                'unsupported_color_count' => 'The group has {count} colors; use 5, 7, 9 or 11.',
                'missing_tones' => 'Tones are missing from the scale: {missing}.',
                'invalid_color' => 'A color value is not a valid HEX.',
                'unknown_reference' => 'The reference “{reference}” does not exist in the file.',
                'reference_not_color' => 'The reference “{reference}” does not point to a color.',
                'reference_cycle' => 'The references form a cycle.',
                'reference_depth' => 'The references exceed the maximum depth of {max}.',
                'algorithm_version_mismatch' => 'The file was generated with algorithm v{found}; colors will be kept as they are.',
                'non_color_ignored' => 'Tokens that are not colors were ignored.',
                'semantic_incomplete' => 'The semantic roles block is incomplete; the current roles will be kept.',
                'extra_colors_ignored' => 'Colors beyond the palette were ignored.',
            ],
        ],
    ],

    'categories' => [
        'json' => 'JSON',
        'sql' => 'SQL',
        'encode' => 'Encoding and hashing',
        'security' => 'Security',
        'time' => 'Date and time',
        'text' => 'Text',
        'badges' => 'Images',
        'colors' => 'Colors',
    ],

    'html-color-picker' => [
        'name' => 'HTML Color Picker',
        'description' => 'Pick a colour, convert it between HEX, RGB, HSL and OKLCH and check WCAG contrast before using it.',
        'keywords' => 'color, colour, hex, rgb, hsl, oklch, contrast, wcag, eyedropper, shades, tints',
        'intro' => 'Pick a colour from the screen or type a code, adjust it on the 2D area and the sliders, and check the WCAG contrast against black and white. Everything runs in your browser: no colour ever leaves the page.',

        'preview' => [
            'alpha' => '{percent}% opacity',
            'eyedropper' => 'Screen eyedropper',
            'eyedropper_image' => 'Eyedropper from an image',
            'gamut' => 'That colour is outside the sRGB gamut. The value you typed ({source}) was reduced in chroma to {value}, keeping lightness and hue.',
        ],

        'image' => [
            'title' => 'Eyedropper from an image',
            'description' => 'The image is read in your browser and never uploaded. Click it or use the arrow keys to choose a pixel, then confirm.',
            'choose' => 'Choose an image',
            'error' => 'That image could not be read. Try another one.',
            'instructions' => 'Click or use the arrow keys to choose a pixel; Enter applies.',
            'apply' => 'Use this colour',
        ],

        'contrast' => [
            'title' => 'WCAG contrast',
            'description' => 'Opacity is composited over the chosen background before the calculation.',
            'backdrop' => 'Background',
            'white' => 'White',
            'black' => 'Black',
            'custom' => 'Custom',
            'black_text' => 'Black text ({ratio}:1 {verdict})',
            'white_text' => 'White text ({ratio}:1 {verdict})',
            'pass_word' => 'pass',
            'fail_word' => 'fail',
            'aa_normal' => 'AA',
            'aaa_normal' => 'AAA',
            'aa_large' => 'AA large',
            'aaa_large' => 'AAA large',
            'large_note' => 'Large text: 18.66 px bold or 24 px regular.',
        ],

        'shades' => [
            'title' => 'Shades',
            'description' => 'Ten darker versions in OKLab, with the same opacity.',
        ],

        'tints' => [
            'title' => 'Tints',
            'description' => 'Ten lighter versions in OKLab, with the same opacity.',
        ],

        'tabs' => [
            'contrast' => 'Contrast',
            'shades' => 'Shades',
            'library' => 'Library',
        ],
    ],

    'palette-generator' => [
        'name' => 'Color Palette Generator',
        'description' => 'Build an 11-tone scale, explore harmonies and extract dominant colours from an image; export as CSS, SCSS, JSON and Tailwind.',
        'keywords' => 'palette, color scale, shades, tints, tailwind, css variables, scss, design tokens, oklch, harmonies, dominant colors, image extraction',
        'intro' => 'Pick a base colour and get an 11-tone OKLCH scale instantly, with WCAG contrast on every tone and the converted values of the base. Explore complementary, analogous, triadic, tetradic and monochromatic harmonies in OKLCH or HSL, and extract up to 11 dominant colours from a local image or an HTTPS URL. Copy one tone or the whole scale and download it as CSS, SCSS, JSON, Tailwind v3 or Tailwind v4. Everything runs in your browser: no colour and no image ever leaves the page.',

        'tabs' => [
            'scale' => 'Scale',
            'harmonies' => 'Harmonies',
            'extraction' => 'Extraction',
            'library' => 'Library',
        ],

        'harmonies' => [
            'title' => 'Harmonies',
            'description' => 'Colours that go with the base. Click one to make it the new base.',
            'kind_label' => 'Harmony type',
            'method_label' => 'Method',
            'method_oklch' => 'OKLCH',
            'method_hsl' => 'HSL',
            'kind_complementary' => 'Complementary',
            'kind_analogous' => 'Analogous',
            'kind_triadic' => 'Triadic',
            'kind_tetradic' => 'Tetradic',
            'kind_monochromatic' => 'Monochromatic',
            'base_badge' => 'Base',
            'clipped_badge' => 'Outside sRGB',
            'achromatic_note' => 'The base is achromatic: rotating the hue creates no new colours, so the harmony is reduced.',
            'color_label' => 'Harmony colour {index}',
            'copy' => 'Copy',
            'use_as_base' => 'Use as base',
            'copied' => 'Copied: {value}',
        ],

        'extraction' => [
            'title' => 'Dominant image colours',
            'description' => 'Extract the dominant colours from a local image or an HTTPS URL. Nothing is sent to the server.',
            'file_label' => 'Image',
            'file_hint' => 'JPEG, PNG, WebP, GIF or AVIF, up to {size}.',
            'url_label' => 'Image URL',
            'url_placeholder' => 'https://example.com/photo.jpg',
            'url_hint' => 'It must be HTTPS and allow CORS; if the origin blocks it, use a local file.',
            'import_url' => 'Import',
            'importing' => 'Importing…',
            'count_label' => 'Number of colours',
            'count_option' => '{count} colours',
            'cancel' => 'Cancel',
            'preview_alt' => 'Preview of the imported image',
            'status' => [
                'reading' => 'Downloading the image…',
                'decoding' => 'Decoding…',
                'analyzing' => 'Extracting dominant colours…',
            ],
            'results_title' => 'Dominant colours · {count}',
            'percentage' => '{value}% of the pixels',
            'percentage_unknown' => 'weight not stored',
            'copy' => 'Copy',
            'copied' => 'Copied: {value}',
            'use_as_base' => 'Use as base',
            'use_file' => 'Use a local file',
            'insufficient' => 'The image has fewer than {requested} distinct colours; {found} were found.',
            'algorithm_version' => 'Algorithm v{version}',
            'source_unknown' => 'source not recorded',
            'source_file' => 'File: {name}',
            'source_url' => 'Image from {host}',
            'errors' => [
                'invalid_url' => 'The URL is not valid.',
                'not_https' => 'Only HTTPS URLs are accepted.',
                'cors' => 'The origin does not allow direct reading (CORS). Use a local file.',
                'network' => 'The image could not be downloaded.',
                'timeout' => 'The image took too long to respond. Try again.',
                'too_large' => 'The image exceeds the configured size limit.',
                'not_image' => 'The address does not point to an accepted image (JPEG, PNG, WebP, GIF or AVIF).',
                'decode_failed' => 'The image could not be decoded.',
                'dimensions_too_large' => 'The image exceeds the configured dimension limits.',
                'too_many_pixels' => 'The image has too many pixels to be analysed.',
                'empty_image' => 'The image has no visible pixels (all transparent).',
                'unavailable' => 'Extraction is not available in this browser.',
                'cancelled' => 'Import cancelled.',
            ],
        ],

        'base' => [
            'title' => 'Base colour',
            'badge' => 'Base',
            'copy' => 'Copy {format}',
            'copied' => 'Copied: {value}',
            'nearest' => 'Nearest tone: {tone}',
            'not_500' => 'The base is not tone 500. It was kept untouched; the scale stays the same.',
            'matches_500' => 'The base is tone 500 of the scale.',
            'clipped' => 'Chroma reduced to fit sRGB.',
            'alpha_note' => 'Contrast is calculated with the tone composited over a background of the same polarity as the text.',
        ],

        'scale' => [
            'title' => '11-tone scale',
            'description' => 'Fixed lightness in OKLCH; chroma follows the base colour.',
            'copy_scale' => 'Copy scale',
            'copied_scale' => 'Scale copied: {count} tones',
            'tone_copy' => 'Copy {tone}',
            'tone_copied' => 'Copied: {value}',
            'contrast_black' => 'Black text {ratio}:1',
            'contrast_white' => 'White text {ratio}:1',
            'pass' => 'passes {level}',
            'fail' => 'fails {level}',
            'clipped_badge' => 'Limited chroma',
            'clipped_summary' => 'Tones with limited chroma: {tones}',
            'algorithm_version' => 'Algorithm v{version}',
            'base_tone_badge' => 'Nearest',
        ],

        'export' => [
            'title' => 'Export',
            'description' => 'Copy or download the scale ready for your project.',
            'name_label' => 'Palette name',
            'name_hint' => 'Used in the variables: {slug}',
            'name_invalid' => 'The name will be normalised to {slug}.',
            'copy' => 'Copy',
            'copied' => '{name} copied',
            'download' => 'Download',
            'semantic_included' => 'Semantic roles from the prototype are included.',
            'tabs' => [
                'css' => 'CSS',
                'scss' => 'SCSS',
                'json' => 'JSON',
                'dtcg' => 'DTCG',
                'style_dictionary' => 'Style Dictionary',
                'typescript' => 'TypeScript',
                'tailwind_v3' => 'Tailwind v3',
                'tailwind_v4' => 'Tailwind v4',
            ],
        ],

        'empty' => [
            'title' => 'Choose a base colour',
            'body' => 'As soon as there is a valid colour, the 11 tones show up here.',
            'restore' => 'Restore example',
        ],

        'state' => [
            'stale' => 'The typed colour is not valid; the previous scale stays on screen.',
        ],

        'save' => [
            'action' => 'Save palette',
            'draft_restored' => 'Your palette is back. Review it and save.',
        ],
    ],

    'layout-harmonizer' => [
        'name' => 'Layout Harmonizer',
        'description' => 'Build the semantic colour tokens for a light and dark interface from one seed: 33 tokens, contrast diagnostics and copyable values.',
        'keywords' => 'layout, semantic tokens, design tokens, color system, light theme, dark theme, contrast, WCAG, OKLCH',
        'intro' => 'Choose a seed colour to get the complete semantic board for the light and dark themes: brand, surfaces, feedback, content and interaction, each with its purpose and measured contrast. Nothing is generated before there is a valid colour, and nothing leaves your browser.',

        'seed' => [
            'title' => 'Seed colour',
            'description' => 'The board is derived from this colour. Type a value or use the picker.',
            'empty_value' => 'No colour yet',
            'background_label' => 'Composition background',
            'background_hint' => 'Required because the seed has transparency. Any format works: HEX, RGB or OKLCH.',
            'background_invalid' => 'This background is not a valid colour.',
            'background_alpha_invalid' => 'The composition background must be opaque: transparency is not accepted here.',
            'summary' => [
                'original' => 'Original',
                'effective' => 'Effective',
                'background' => 'Background',
                'strategy' => 'Strategy',
                'secondary' => 'Secondary',
                'alpha_note' => 'With transparency, the effective seed is the colour composed over the background.',
                'achromatic' => 'The seed is achromatic: hue rotations are reduced to the neutral tone and the board stays achromatic.',
            ],
        ],

        'strategy' => [
            'label' => 'Harmony strategy',
            'hint' => 'The 33 tokens stay the same; the strategy changes how the secondary and accent tones are derived.',
            'analogous' => [
                'name' => 'Analogous',
                'description' => 'Secondary 30° from the seed: close neighbours, little tension.',
            ],
            'complementary' => [
                'name' => 'Complementary',
                'description' => 'Secondary 180° from the seed: maximum contrast.',
            ],
            'triadic' => [
                'name' => 'Triadic',
                'description' => 'Secondary 240° from the seed: three evenly spaced hues.',
            ],
            'monochromatic' => [
                'name' => 'Monochromatic',
                'description' => 'Secondary on the same hue: variation only in lightness and chroma.',
            ],
        ],

        'view' => [
            'light' => 'Light',
            'dark' => 'Dark',
            'compare' => 'Compare',
            'board_light' => 'Light theme',
            'board_dark' => 'Dark theme',
        ],

        'families' => [
            'brand' => 'Brand',
            'surface' => 'Surfaces',
            'feedback' => 'Feedback',
            'content' => 'Content',
            'interaction' => 'Interaction',
        ],

        'tokens' => [
            'primary' => 'Main brand colour; base for solid buttons and emphasis.',
            'primary-variant' => 'Deeper brand tone for large blocks, separate from hover.',
            'secondary' => 'Supporting colour for less prominent actions.',
            'secondary-variant' => 'Deeper secondary tone for larger surfaces.',
            'surface' => 'Default panel background.',
            'surface-variant' => 'Subtle background for grouped content.',
            'background' => 'Page background, the outermost layer.',
            'hairline' => 'Borders and dividers.',
            'error' => 'Background and text colour for the error state.',
            'success' => 'Background and text colour for the success state.',
            'warning' => 'Background and text colour for the warning state.',
            'info' => 'Background and text colour for the informational state.',
            'on-primary' => 'Text and icons over primary, hover and active.',
            'on-secondary' => 'Text and icons over secondary and its states.',
            'on-background' => 'Main text over the page background.',
            'on-surface' => 'Text over surfaces and cards.',
            'on-surface-variant' => 'Secondary text: captions, hints and metadata.',
            'on-error' => 'Text and icons over error.',
            'on-success' => 'Text and icons over success.',
            'on-warning' => 'Text and icons over warning.',
            'on-info' => 'Text and icons over info.',
            'primary-hover' => 'Primary on hover and pointer states.',
            'primary-active' => 'Primary while pressed.',
            'secondary-hover' => 'Secondary on hover.',
            'secondary-active' => 'Secondary while pressed.',
            'link' => 'Links inside content.',
            'link-hover' => 'Link on hover.',
            'link-active' => 'Link while pressed.',
            'focus-ring' => 'Keyboard focus ring.',
            'accent' => 'Third colour of the harmony, present in every strategy.',
            'on-accent' => 'Text and icons over accent and its states.',
            'accent-hover' => 'Accent on hover.',
            'accent-active' => 'Accent while pressed.',
        ],

        'provenance' => [
            'seed' => 'From seed',
            'manual' => 'Manual',
            'rule' => 'By rule',
            'feedback' => 'Feedback',
        ],

        'contrast' => [
            'aa' => 'AA',
            'aa_fail' => 'AA fails',
            'against' => 'Against {background}',
            'on' => 'on',
            'summary_title' => 'AA contrast',
            'all_pass' => 'Every measured pair reaches AA for normal text.',
        ],

        'copy' => [
            'hex_copied' => 'Copied {token}: HEX',
            'oklch_copied' => 'Copied {token}: OKLCH',
            'hex_aria' => 'Copy HEX of {token}',
            'oklch_aria' => 'Copy OKLCH of {token}',
        ],

        'clipped' => [
            'badge' => 'Reduced chroma',
            'note' => 'The requested chroma is outside sRGB and was reduced to the closest displayable colour.',
        ],

        'states' => [
            'empty_title' => 'No board yet',
            'empty_description' => 'Choose a seed colour to generate the light and dark boards.',
            'invalid_seed' => 'The typed colour is not valid; the previous board stays on screen.',
            'seed_alpha_without_background' => 'The seed has transparency: enter a composition background to generate the board.',
            'seed_background_alpha_invalid' => 'The seed background has transparency: enter an opaque colour.',
            'secondary_alpha_without_background' => 'The secondary has transparency: enter an opaque composition background to generate the board.',
            'invalid_strategy' => 'The chosen strategy is not recognised; the previous board stays on screen.',
            'stale_board' => 'The board shown reflects the last valid input.',
        ],

        'edit' => [
            'title' => 'Edit {token}',
            'open_aria' => 'Edit {token}',
            'apply' => 'Apply',
            'cancel' => 'Cancel',
            'alpha_ignored' => 'Tokens are opaque: the alpha you choose is ignored.',
        ],

        'lock' => [
            'lock' => 'Lock',
            'unlock' => 'Unlock',
            'lock_aria' => 'Lock {token}',
            'unlock_aria' => 'Unlock {token}',
        ],

        'restore' => [
            'token' => 'Restore token',
            'token_aria' => 'Restore {token} to automatic',
            'theme' => 'Restore {theme} ({count})',
            'all' => 'Restore all ({count})',
            'confirm_title' => 'Discard manual changes?',
            'confirm_body' => 'This removes manual values and locks from {count} token(s) in this scope and returns to automatic generation.',
            'confirm' => 'Restore',
            'cancel' => 'Cancel',
        ],

        'secondary' => [
            'label' => 'Secondary colour (optional)',
            'hint' => 'Empty keeps the automatic secondary from the strategy. Variants and states are still derived per theme.',
            'invalid' => 'This secondary is not a valid colour; the last valid value stays.',
            'background_label' => 'Composition background',
            'background_hint' => 'Required because the secondary has transparency. Any format works: HEX, RGB or OKLCH.',
            'background_invalid' => 'This background is not a valid colour.',
            'background_alpha_invalid' => 'The composition background must be opaque: transparency is not accepted here.',
            'manual' => 'Manual secondary',
            'auto' => 'Automatic secondary',
            'reset' => 'Back to automatic',
        ],

        'suggestions' => [
            'title' => 'Contrast suggestions',
            'description' => 'Each suggestion moves only the text colour, by the smallest lightness step that reaches the chosen target against every background of that token. Nothing is applied without your acceptance.',
            'badge' => 'Suggestion',
            'target' => [
                'aa' => 'AA target',
                'aaa' => 'AAA target',
            ],
            'none' => 'Every measured pair reaches the chosen target.',
            'delta_lightness' => 'ΔL {delta}',
            'accept_theme' => 'Accept in {theme}',
            'accept_both' => 'Accept in both themes',
            'inviable' => 'No lightness reaches {target} against every background of this token.',
            'inviable_badge' => 'Not reachable',
        ],

        'edit_notice' => [
            'discarded' => 'Unlocked manual values were discarded because the seed, strategy or secondary changed. Locked values were kept.',
        ],

        'feedback_preview' => [
            'title' => 'Feedback pairs',
            'description' => 'Each feedback colour with the content colour measured on top of it.',
        ],

        'a11y' => [
            'view_mode' => 'Theme view',
            'board_section' => 'Board: {theme}',
            'contrast_summary' => 'Contrast diagnostics',
            'restore' => 'Restore automatic generation',
            'suggestions' => 'Contrast suggestions',
            'suggestion_target' => 'Contrast target for suggestions',
        ],

        'prototype' => [
            'title' => 'Prototype',
            'description' => 'See the system\'s 33 tokens applied to an interface, in the template and theme you pick.',
            'template_label' => 'Template',
            'template_site' => 'Site',
            'template_mobile' => 'Mobile',
            'theme_label' => 'Theme',
            'theme_light' => 'Light',
            'theme_dark' => 'Dark',
            'preview_label' => 'Prototype preview: {template}, {theme}',
        ],

        'save' => [
            'title' => 'Save layout system',
            'description' => 'The saved board reopens exactly as it is, without recalculating.',
            'name_label' => 'Name',
            'name_placeholder' => 'My system',
            'name_required' => 'Enter a name.',
            'name_too_long' => 'Use at most {max} characters.',
            'name_from_palette' => 'Layout system — {name}',
            'name_from_strategy' => '{strategy} — {hex}',
            'description_label' => 'Description (optional)',
            'description_placeholder' => 'What is this system for?',
            'description_too_long' => 'Use at most {max} characters.',
            'visibility_label' => 'Who can see it',
            'visibility_private' => 'Private',
            'visibility_private_hint' => 'Only you see it. You can publish later.',
            'visibility_public' => 'Public',
            'visibility_public_hint' => 'It gets a public link and shows up in search.',
            'save_button' => 'Save',
            'submit' => 'Save system',
            'submit_update' => 'Save changes',
            'save_as_new' => 'Save as new',
            'saving' => 'Saving…',
            'cancel' => 'Cancel',
            'login_to_save' => 'Sign in to save',
            'limit_reached' => 'You reached the limit of {limit} items. Archive or delete something to save.',
        ],

        'draft' => [
            'title' => 'Resume the draft?',
            'description' => 'We found a board saved in this browser before you signed in. Importing keeps your work and does not create a system.',
            'unsupported_title' => 'Draft from a newer version',
            'unsupported_description' => 'This draft was created by a newer version of the tool and cannot be imported here. You can discard it.',
            'import' => 'Import draft',
            'discard' => 'Discard',
            'discard_stored' => 'Discard saved draft',
            'discard_title' => 'Discard the saved draft?',
            'discard_body' => 'The board saved in this browser is deleted and the tool starts empty.',
        ],

        'origin' => [
            'from' => 'Created from the palette {name}.',
            'open' => 'View palette',
            'remove' => 'Remove origin',
        ],

        'lifecycle' => [
            'restore_disabled' => 'Restore is unavailable for this system: the saved state does not keep the automatic values. Use “Regenerate with the current version”.',
            'suggestions_off' => 'Contrast suggestions are unavailable for this system because the suggestion algorithm version changed.',
            'pending_regeneration' => 'The seed, the strategy or the secondary changed. The board stays in the saved state until you regenerate.',
            'regenerate' => 'Regenerate with the current version',
            'regenerate_available' => 'This system was saved with an older version. The board stays exactly as saved; regenerating applies the current version to the same inputs.',
            'regenerate_confirm_title' => 'Regenerate with the current version?',
            'regenerate_confirm_body' => 'Locked tokens are kept. {count} unlocked manual or accepted value(s) will be discarded.',
            'readonly_notice' => 'This system uses a newer version than this editor. The board is read-only; duplicate it to keep editing.',
        ],

        'guard' => [
            'title' => 'Leave without saving?',
            'description' => 'You have changes that have not been saved. If you leave now, they will be lost.',
            'stay' => 'Keep editing',
            'leave' => 'Leave without saving',
        ],

        'export' => [
            'title' => 'Export',
            'description' => 'Download the board on screen as instructions for an AI, as CSS variables, or both in a ZIP. Everything is generated here, in your browser, without regenerating colours.',
            'login_cta' => 'Sign in to export',
            'login_hint' => 'Creating an account is free. Your board stays exactly as it is — you do not need to save it to export.',
            'language_label' => 'Document language',
            'prefix_label' => 'CSS prefix',
            'prefix_hint' => 'Variables become `--{prefix}-primary`, `--{prefix}-background` and so on.',
            'scope_label' => 'CSS scope',
            'scope_root' => 'Document root (:root)',
            'scope_class' => 'Custom class',
            'scope_class_label' => 'Class name',
            'download_markdown' => 'Download Markdown',
            'download_css' => 'Download CSS',
            'download_zip' => 'Download ZIP',
            'pending_regeneration' => 'The inputs changed after the last generation. Regenerate the board before exporting.',
            'compatible_notice' => 'This state was generated by an older algorithm. The files carry the saved versions.',
            'readonly_notice' => 'This state is read-only. You can still export the displayed values.',
            'errors' => [
                'prefix_required' => 'Enter a prefix.',
                'prefix_too_long' => 'Use at most {max} characters.',
                'prefix_invalid' => 'Use lowercase letters, digits and single hyphens, starting with a letter.',
                'scope_required' => 'Enter a scope.',
                'scope_too_long' => 'Use at most {max} characters.',
                'scope_invalid' => 'Use :root or a simple class such as .app.',
            ],
            'targets' => [
                'aa' => 'AA',
                'aaa' => 'AAA',
            ],
            'css' => [
                'header' => 'Layout Harmonizer — {name}',
                'meta' => '{language} · State {state} · Schema {schema} · Algorithm {algorithm} · Suggestions {suggestions} · Snapshot {snapshot}',
                'theme_default' => 'Default theme: light. For the dark theme, set data-theme="dark" on the scope.',
            ],
            'warnings' => [
                'title' => 'Warnings',
                'none' => 'No warnings.',
                'contrast' => '{theme}: {foreground} over {background} — {ratio} (target {target})',
                'locked' => '{theme}: {token} is locked.',
                'manual' => '{theme}: {token} was set manually ({hex}).',
                'accepted' => '{theme}: {token} adjusted from {from} to {to} (target {target}).',
                'clipped' => '{theme}: {token} chroma reduced to fit sRGB.',
                'compatible' => 'Compatible mode: algorithm {algorithm} and suggestions {suggestions} differ from the current editor.',
                'readonly' => 'Read-only state: schema {schema}, algorithm {algorithm}, suggestions {suggestions}.',
                'limitations' => 'Contrast targets are advisory: failures are reported and colours are never changed automatically.',
            ],
            'md' => [
                'purpose' => 'Design instructions for an interface built with this semantic colour system. Use only the tokens listed here and keep their purposes.',
                'metadata_title' => 'Metadata',
                'language' => 'Language: {language}',
                'strategy' => 'Strategy: {strategy}',
                'target' => 'Contrast target: {target}',
                'seed_original' => 'Original seed: {hex}',
                'seed_effective' => 'Effective seed: {hex}',
                'seed_background' => 'Composition background: {hex}',
                'secondary' => 'Secondary: {mode}',
                'secondary_auto' => 'automatic',
                'secondary_manual' => 'manual',
                'secondary_original' => 'Original secondary: {hex}',
                'secondary_effective' => 'Effective secondary: {hex}',
                'secondary_background' => 'Secondary composition background: {hex}',
                'versions' => 'Versions: state {state}, schema {schema}, algorithm {algorithm}, suggestions {suggestions}, snapshot {snapshot}',
                'origin' => 'Origin: {name} (public palette).',
                'instructions_title' => 'Instructions for the AI',
                'instructions_intro' => 'Apply the tokens below to the interface. Do not invent colours or hard-code values outside this list.',
                'instructions_semantic' => 'Use the semantic tokens instead of raw palette colours: `background` for the page, `surface` for panels, `on-*` for text and icons.',
                'instructions_manual' => 'Tokens marked as manual or locked must stay exactly as listed; do not recalculate them.',
                'instructions_contrast' => 'Keep the listed pairs above the {target} target; when a pair fails, report it instead of changing colours.',
                'instructions_map' => 'Map backgrounds to `background` and `surface`, text to `on-background`, `on-surface` and `on-surface-variant`, buttons to `primary`, `secondary` and `accent` with their `-hover` and `-active` states, links to `link`, `link-hover` and `link-active`, feedback to `error`, `success`, `warning` and `info` with their `on-*`, and focus outlines to `focus-ring`.',
                'themes_title' => 'Themes',
                'themes_note' => 'The light theme is the default; the dark theme applies when the interface declares data-theme="dark".',
                'tokens_title' => 'Tokens — {theme}',
                'columns' => [
                    'token' => 'Token',
                    'purpose' => 'Purpose',
                    'hex' => 'HEX',
                    'oklch' => 'OKLCH',
                    'source' => 'Source',
                    'locked' => 'Locked',
                    'foreground' => 'Foreground',
                    'background' => 'Background',
                    'ratio' => 'Ratio',
                    'aa' => 'AA',
                    'aaa' => 'AAA',
                ],
                'locked_yes' => 'Yes',
                'locked_no' => 'No',
                'rules_title' => 'Application rules',
                'rules' => [
                    'backgrounds' => 'Use `background` for the page and `surface` for cards and panels; `surface-variant` groups nested content and `hairline` draws borders and dividers.',
                    'text' => 'Use `on-background` for main text, `on-surface` for text on cards and `on-surface-variant` for captions, hints and metadata.',
                    'buttons' => 'Solid buttons use `primary`; supporting actions use `secondary`; the third harmony colour is `accent`. Hover and pressed states use the matching `-hover` and `-active` tokens, with text in the `on-*` token of that family.',
                    'links' => 'Links use `link`, `link-hover` and `link-active` inside content, never another brand colour directly.',
                    'feedback' => 'Feedback messages use `error`, `success`, `warning` or `info` as the background, with text and icons in the matching `on-*` token.',
                    'focus' => 'Keyboard focus uses `focus-ring` as the outline, never a colour that is not in this list.',
                ],
                'contrast_title' => 'Contrast — {theme}',
                'pass' => 'Pass',
                'fail' => 'Fail',
                'generated_title' => 'Generated',
                'generation_note' => 'Generated in the browser from the displayed snapshot (state {state}, snapshot {snapshot}). No AI was called and nothing was sent to the server.',
            ],
        ],
    ],

    'json-viewer' => [
        'name' => 'JSON Viewer',
        'description' => 'Explore, format and validate JSON as a tree, with search and copy of any path.',
        'keywords' => 'format, pretty print, minify, validate, tree, path',
        'intro' => 'Paste JSON to see the colour-coded tree, format, minify and copy the path of any node. Everything runs in your browser: nothing you paste here is sent to the server.',

        'format' => 'Format',
        'minify' => 'Minify',
        'sort_keys' => 'Sort keys',
        'expand_all' => 'Expand all',
        'collapse_all' => 'Collapse all',
        'download_name' => 'document',
        'file_types' => 'JSON or TXT',

        'tree' => [
            'toggle' => 'Collapse or expand',
            'copy_path' => 'Copy path',
            'copied' => 'Path copied: {path}',
        ],

        'search' => [
            'placeholder' => 'Search key or value',
            'label' => 'Search the tree',
            'clear' => 'Clear search',
            'results' => 'Results: {count}',
            'truncated' => 'Showing the first {count} results.',
            'none' => 'No results.',
        ],

        'stats' => [
            'keys' => 'Keys: {count}',
            'items' => 'Items: {count}',
        ],

        'shortcuts' => [
            'format' => 'Format the document',
            'search' => 'Search the tree',
            'clear' => 'Clear the search',
        ],
    ],

    'ai-sql-builder' => [
        'name' => 'AI SQL Builder',
        'description' => 'Describe the query in plain language and get SQL ready for your dialect.',
        'keywords' => 'natural language, generate query, database',
        'intro' => 'Describe the query, paste the schema and pick the output: SQL, Eloquent, Prisma and more. This is the one tool that leaves the browser: the schema and the description go to the AI provider and spend the plan quota. The generated SQL is never executed here.',
    ],

    'json-to-code' => [
        'name' => 'JSON to code',
        'description' => 'Turn JSON into TypeScript types, PHP DTOs and Python models.',
        'keywords' => 'generate types, interface, dto, pydantic',
        'intro' => 'Paste JSON and pick a target: TypeScript interfaces, PHP DTOs with promoted properties, or Pydantic models. The inference runs in your browser: nothing you paste here is sent to the server.',

        'target' => 'Target language',
        'root_name' => 'Root type name',
        'download_name' => 'types',
        'file_types' => 'JSON or TXT',

        'targets' => [
            'typescript' => 'TypeScript',
            'php' => 'PHP (DTO)',
            'pydantic' => 'Pydantic',
        ],

        'states' => [
            'root_object' => 'The root must be a JSON object with at least one key.',
        ],

        'stats' => [
            'types' => 'Types: {count}',
        ],
    ],

    'sql-formatter' => [
        'name' => 'SQL Formatter',
        'description' => 'Format SQL queries for the MySQL, PostgreSQL, SQL Server and SQLite dialects.',
        'keywords' => 'beautify, indent, query',
        'intro' => 'Paste the query and pick a dialect: the SQL comes out indented on the right, and the case of the keywords is your call. The formatting runs in your browser: nothing you paste here is sent to the server.',

        'dialect' => 'Dialect',
        'keyword_case' => 'Keyword case',
        'download_name' => 'query',
        'file_types' => 'SQL or TXT',

        'dialects' => [
            'mysql' => 'MySQL',
            'pgsql' => 'PostgreSQL',
            'sqlsrv' => 'SQL Server',
            'sqlite' => 'SQLite',
        ],

        'cases' => [
            'upper' => 'UPPERCASE',
            'lower' => 'lowercase',
            'preserve' => 'Keep',
        ],
    ],

    'base64-url-hash' => [
        'name' => 'Base64, URL and Hash',
        'description' => 'Encode and decode Base64 and URL, and generate MD5, SHA-1, SHA-256 and SHA-512 hashes.',
        'keywords' => 'encode, decode, checksum, digest',
        'intro' => 'One input, three readings: the same text becomes Base64, becomes an encoded URL, or becomes a hash. Pick the tab on the right and the direction on the left. Everything runs in your browser: nothing you paste here is sent to the server.',

        'direction' => 'Direction',
        'file_types' => 'TXT',
        'row_copied' => '{label} copied',

        'directions' => [
            'encode' => 'Encode',
            'decode' => 'Decode',
        ],

        'modes' => [
            'base64' => 'Base64',
            'url' => 'URL',
            'hash' => 'Hash',
        ],

        'rows' => [
            'base64' => 'Base64',
            'urlSafe' => 'URL-safe Base64',
            'component' => 'Component',
            'form' => 'Form',
            'md5' => 'MD5',
            'sha1' => 'SHA-1',
            'sha256' => 'SHA-256',
            'sha512' => 'SHA-512',
        ],

        'states' => [
            'invalid_base64' => 'This is not the Base64 of a text: there is a character outside the alphabet, a stray character at the end, or bytes that are not UTF-8.',
            'invalid_url' => 'This is not a valid percent-encoded URL: there is a broken escape sequence or bytes that are not UTF-8.',
            'no_crypto' => 'This browser only exposes Web Crypto over a secure connection (HTTPS), so the three SHA lines have no value here. MD5 still works — but remember: MD5 is a checksum, not a signature.',
        ],
    ],

    'jwt-decoder' => [
        'name' => 'JWT Decoder',
        'description' => 'Read the header and the payload of a JWT. The signature is not validated.',
        'keywords' => 'token, bearer, claims, authentication',
        'intro' => 'Paste the token (the Bearer prefix may come along) to read the header, the payload and the dates of the claims. The tool decodes and does not validate the signature, and everything runs in your browser: nothing you paste here is sent to the server.',

        'header' => 'Header',
        'payload' => 'Payload',
        'signature' => 'Signature',
        'signature_bytes' => '{count} bytes',
        'not_verified' => 'not verified',
        'block_copied' => '{label} copied',

        'warning' => 'This decoder does not validate the signature: it shows what is written inside the token, and nothing here proves the token is authentic.',

        'claims_title' => 'Dates',

        'claims' => [
            'expired' => 'expired',
            'not_yet' => 'not valid yet',
        ],

        'states' => [
            'shape' => 'This is not a JWT: a JWT is three parts separated by dots (header, payload and signature), and there are not three here.',
            'base64' => 'One of the parts is not valid base64url, or its bytes are not UTF-8.',
            'json' => 'The header or the payload is not a JSON object.',
        ],
    ],

    'timestamp-uuid' => [
        'name' => 'Timestamp and UUID',
        'description' => 'Convert Unix timestamps and dates, and generate UUID and ULID values.',
        'keywords' => 'unix, epoch, date, guid',
        'intro' => 'Paste an instant — Unix in seconds or milliseconds, a date, or a UUID v7/ULID — to see its six readings. The panel on the right also generates UUID v4, v7 and ULID. Everything runs in your browser: nothing you paste here is sent to the server.',

        'now' => 'Now',
        'kind' => 'Id type',
        'quantity' => 'Quantity',
        'generate' => 'Generate',
        'ids_title' => 'Generated ids: {count}',
        'copy_ids' => 'Copy all',
        'ids_copied' => '{count} ids copied',
        'id_copied' => 'Id copied',
        'row_copied' => '{label} copied',
        'recognised' => 'The pasted id carries this instant ({kind})',

        'kinds' => [
            'uuid_v4' => 'UUID v4',
            'uuid_v7' => 'UUID v7',
            'ulid' => 'ULID',
        ],

        'readings' => [
            'unix_seconds' => 'Unix (seconds)',
            'unix_ms' => 'Unix (milliseconds)',
            'iso_utc' => 'ISO 8601 (UTC)',
            'iso_local' => 'ISO 8601 (local)',
            'long_date' => 'Written-out date',
            'relative' => 'Relative time',
        ],

        'states' => [
            'unreadable' => 'No instant could be read here: use Unix in seconds or milliseconds, a date (ISO 8601) or a UUID v7/ULID.',
        ],
    ],

    'regex-tester' => [
        'name' => 'Regex Tester',
        'description' => 'Test regular expressions with live highlighting of matches and groups.',
        'keywords' => 'pattern, match, groups',
        'intro' => 'Type the regular expression in the top bar and the test text in the left panel: the matches come out highlighted, one by one, with numbered and named groups, and the replacement field shows the rewritten text. The search runs in your browser: nothing you paste here is sent to the server.',

        'pattern' => 'Regular expression',
        'replacement' => 'Replace with',
        'replacement_hint' => 'E.g. $1 or $<name>',
        'position' => 'Position {start}',
        'matches_count' => 'Matches: {count}',
        'matches_none' => 'No matches.',
        'truncated' => 'Showing the first {count} matches.',
        'replaced_title' => 'Replacement result',
        'file_types' => 'TXT',

        'flags' => [
            'i' => 'Ignore case',
            'm' => 'Multiline: ^ and $ on every line',
            's' => 'The dot also matches the newline',
            'u' => 'Unicode: match by code point',
        ],

        'states' => [
            'pattern' => 'The expression is not valid. The detail below is the browser engine message.',
            'pattern_empty' => 'Type the regular expression to test.',
            'timeout' => 'The search went past {seconds} seconds and was stopped: no result is shown. Narrow the expression to avoid the backtracking.',
        ],
    ],

    'markdown-to-pdf' => [
        'name' => 'Markdown to PDF',
        'description' => 'Convert Markdown to styled PDF documents with real-time preview, custom page formats, fonts and themes.',
        'keywords' => 'markdown, pdf, converter, document, export, gfm, print',
        'intro' => 'Paste or open Markdown to preview formatted documents in real time. Customise page sizes, fonts and themes, then download your PDF or print with vector precision. Everything runs in your browser: nothing you paste here is sent to the server.',

        'download_name' => 'document',
        'file_types' => 'MD or TXT',

        'controls' => [
            'format' => 'Format',
            'orientation' => 'Orientation',
            'margin' => 'Margin',
            'font' => 'Font',
            'font_size' => 'Size',
            'theme' => 'Theme',
        ],

        'formats' => [
            'a4' => 'A4',
            'letter' => 'Letter',
            'a5' => 'A5',
        ],

        'orientations' => [
            'portrait' => 'Portrait',
            'landscape' => 'Landscape',
        ],

        'margins' => [
            'normal' => 'Normal (20mm)',
            'compact' => 'Compact (10mm)',
            'wide' => 'Wide (30mm)',
        ],

        'fonts' => [
            'sans' => 'Sans-serif',
            'serif' => 'Serif',
            'mono' => 'Monospace',
            'fira-code' => 'Fira Code',
        ],

        'font_sizes' => [
            'sm' => 'Small',
            'md' => 'Medium',
            'lg' => 'Large',
        ],

        'themes' => [
            'modern' => 'Modern',
            'editorial' => 'Editorial',
            'minimal' => 'Minimalist B&W',
            'academic' => 'Academic',
        ],

        'actions' => [
            'download_pdf' => 'Download PDF',
            'print' => 'Print / Save as PDF',
            'generating' => 'Generating...',
        ],

        'stats' => [
            'words' => 'Words: {count}',
            'chars' => 'Characters: {count}',
        ],
    ],

    'base64-image' => [
        'name' => 'Base64 Image Encoder / Decoder',
        'description' => 'Convert images into Base64 Data URI (pure text, img tag, or CSS) and decode Base64 back into images.',
        'intro' => 'Convert images to Base64 Data URI with img tag and CSS snippet support, or decode Base64 strings back to images with instant preview and download.',
        'keywords' => 'image, base64, data-uri, encoder, decoder, converter, css, html',
        'upload_title' => 'Click to select or drag and drop an image',
        'upload_subtitle' => 'Supports PNG, JPG, GIF, WebP, SVG, BMP, AVIF and more formats',
        'max_limit_badge' => 'Limit: {limit}',
        'pro_plan_badge' => 'Pro Plan (Up to 25 MB)',
        'converting' => 'Converting image on server...',
        'converting_hint' => 'Please wait while the file is processed.',
        'new_image' => 'New Image',
        'original_size' => 'Original size',
        'base64_size' => 'Base64 size',
        'retention_info' => 'History retained for {hours}h',
        'retention_info_day' => 'History retained for {days} day',
        'retention_info_days' => 'History retained for {days} days',
        'direct_download_notice_title' => 'Large file converted',
        'direct_download_notice_desc' => 'To protect browser memory, downloading the .txt file with Data URI started directly.',
        'copy' => 'Copy',
        'copied' => 'Copied!',
        'download_txt' => 'Download .txt',
        'download_json' => 'Download .json',
        'download_xml' => 'Download .xml',
        'download_image' => 'Download Image',
        'preview_img' => 'Preview',
        'size' => 'Size',
        'example' => 'Example',
        'clear' => 'Clear',
        'decoder_input_label' => 'Paste Base64 string or Data URI:',
        'decoder_output_label' => 'Decoded Image:',
        'decoder_placeholder' => 'data:image/png;base64,iVBORw0KGgo...',
        'decoder_empty_prompt' => 'No decoded image yet.',
        'decoder_empty_hint' => 'Paste a valid Base64 string on the left to preview and download.',
        'history_empty_title' => 'No recent conversions',
        'history_empty_desc' => 'Images you convert will appear here during their retention period.',
        'active_badge' => 'Active',
        'expired_badge' => 'Expired',
        'confirm_delete' => 'Are you sure you want to delete this conversion from history?',
        'tabs' => [
            'encoder' => 'Encoder (Image → Base64)',
            'decoder' => 'Decoder (Base64 → Image)',
            'history' => 'History',
        ],
        'formats' => [
            'data_uri' => 'Data URI / Pure Text',
            'img_tag' => 'HTML <img> Tag',
            'css_bg' => 'CSS background-image',
            'json' => 'JSON',
            'xml' => 'XML',
        ],
        'errors' => [
            'title' => 'Conversion error',
            'invalid_image' => 'The uploaded file is not a valid image.',
            'file_too_large' => 'The file exceeds the maximum allowed limit of {limit}.',
            'conversion_failed' => 'An error occurred while processing the image. Please try again.',
            'invalid_base64' => 'The provided Base64 string is invalid or corrupted.',
            'file_expired' => 'The file has expired and was removed from the server.',
        ],
    ],
    'share-text' => [
        'name' => 'Share Text',
        'description' => 'Share texts, notes, and code snippets with short ULID links and full Hastebin compatibility.',
        'intro' => 'Share notes, code snippets, and text with short ULID links. Anonymous visitors get 72-hour retention, while registered users enjoy permanent storage with full Hastebin API compatibility.',
        'keywords' => 'hastebin, pastebin, share text, snippet, code, notes, ulid, raw',
        'placeholder' => 'Paste or type your text here...',
        'actions' => [
            'save' => 'Save',
            'new' => 'New',
            'duplicate' => 'Duplicate & Edit',
            'raw' => 'Raw Text',
            'copy_link' => 'Copy Link',
            'copied' => 'Link copied!',
            'history' => 'History',
        ],
        'badges' => [
            'anonymous' => 'Expires in 72h (anonymous)',
            'permanent' => 'Permanent (your account)',
            'draft' => 'Unsaved draft',
        ],
        'dialog' => [
            'title' => 'Text Shared Successfully',
            'description' => 'Your text has been saved with a unique identifier and is ready for sharing.',
            'url_label' => 'Share link:',
            'raw_url_label' => 'Direct link (Raw):',
            'close' => 'Close',
        ],
        'history' => [
            'title' => 'Your Shared Texts',
            'empty' => 'No shared texts yet.',
            'open' => 'Open',
            'delete' => 'Delete',
            'confirm_delete' => 'Are you sure you want to permanently delete this text?',
        ],
        'languages' => [
            'auto' => 'Plain Text',
            'json' => 'JSON',
            'sql' => 'SQL',
            'markdown' => 'Markdown',
        ],
        'errors' => [
            'empty_content' => 'Content cannot be empty.',
            'size_exceeded' => 'Text exceeds the maximum allowed limit of :max MB.',
            'save_failed' => 'Failed to save text. Please try again.',
            'not_found' => 'Text not found or expired.',
        ],
        'messages' => [
            'deleted' => 'Text successfully deleted.',
        ],
    ],

    'badgeforge' => [
        'name' => 'BadgeForge',
        'description' => 'Create SVG badges for your READMEs with live preview and ready snippets for Markdown, HTML and reStructuredText.',
        'keywords' => 'badge, shields, svg, readme, markdown, ci, coverage',
        'intro' => 'Build the badge, check the preview and copy the snippet. The image is served as SVG by this site; nothing you type here leaves the browser except the URL you copy.',

        'fields' => [
            'label' => 'Label',
            'message' => 'Message',
            'style' => 'Style',
            'color' => 'Color',
            'label_color' => 'Label color',
            'logo' => 'Logo',
            'logo_color' => 'Logo color',
            'logo_size' => 'Logo size',
            'logo_position' => 'Logo position',
            'link' => 'Link',
            'cache_seconds' => 'Cache (seconds)',
            'font' => 'Font',
            'format' => 'Format',
        ],

        'placeholders' => [
            'label' => 'build',
            'message' => 'passing',
            'color' => '#9f9f9f or green',
            'label_color' => '#555555',
            'logo_color' => '#ffffff',
            'link' => 'https://example.com',
        ],

        'positions' => [
            'left' => 'Left',
            'right' => 'Right',
        ],

        'logo_none' => 'No logo',

        'fonts' => [
            'default' => 'Style default',
        ],

        'formats' => [
            'svg' => 'SVG',
            'png' => 'PNG',
        ],

        'sections' => [
            'content' => 'Content',
            'colors' => 'Colors',
            'logo' => 'Logo',
            'advanced' => 'Advanced',
        ],

        'preview' => [
            'title' => 'Preview',
            'dark' => 'Dark background',
            'light' => 'Light background',
            'empty' => 'Type a label and a message to see the badge.',
            'dimensions' => '{width} × {height} px',
        ],

        'snippets' => [
            'title' => 'Embed',
            'url' => 'URL',
            'markdown' => 'Markdown',
            'html' => 'HTML',
            'rst' => 'reStructuredText',
        ],

        'actions' => [
            'sample' => 'Example',
            'reset' => 'Reset',
        ],

        'management' => [
            'logos_button' => 'My logos',
            'presets_button' => 'Presets',
            'login_cta' => 'Sign in to upload your own logos and save presets.',
            'login_action' => 'Sign in',
            'upgrade_cta' => 'Your plan allows {limit} custom logos. Upgrade for unlimited logos.',
            'upgrade' => 'Upgrade plan',
            'limit_note' => 'Custom logos used: {used} of {limit}.',
            'unlimited_note' => 'Custom logos used: {used} (unlimited).',
            'preset_limit_note' => 'Presets used: {used} of {limit}.',
            'preset_unlimited_note' => 'Presets used: {used} (unlimited).',
            'logo_created' => 'Logo saved.',
            'logo_updated' => 'Logo renamed.',
            'logo_deleted' => 'Logo deleted.',
            'preset_created' => 'Preset saved.',
            'preset_updated' => 'Preset renamed.',
            'preset_deleted' => 'Preset deleted.',
            'throttled' => 'Too many actions. Wait a moment and try again.',
            'badge_created' => 'Badge created.',
            'badge_updated' => 'Badge renamed.',
            'badge_credential_updated' => 'Credential updated. The badge will be generated again.',
            'badge_deleted' => 'Badge deleted.',
            'logos_title' => 'My logos',
            'logos_description' => 'SVG files made of paths only. The file itself is not stored; only the normalized paths are.',
            'presets_title' => 'Presets',
            'presets_description' => 'Snapshots of the builder form. Apply one to fill the form again.',
            'empty_logos' => 'You have not uploaded any logo yet.',
            'empty_presets' => 'You have not saved any preset yet.',
            'upload' => 'Upload SVG',
            'uploading' => 'Uploading...',
            'upload_hint' => 'SVG up to {size} KB with viewBox and simple vector shapes (path, rect, circle, ellipse, polygon, polyline, line and g; no text, style, transform or embedded images).',
            'logo_file' => 'SVG file',
            'name' => 'Name',
            'name_placeholder' => 'My logo',
            'preset_name_placeholder' => 'My preset',
            'save' => 'Save',
            'cancel' => 'Cancel',
            'rename' => 'Rename',
            'delete' => 'Delete',
            'delete_title' => 'Delete "{name}"?',
            'delete_description' => 'This action cannot be undone.',
            'preview_loading' => 'Loading preview...',
            'preview_error' => 'Could not load the preview.',
            'path_count' => '{count} paths',
            'preview' => 'Preview',
            'save_preset' => 'Save as preset',
            'apply_preset' => 'Apply',
            'preset_orphaned' => 'The logo in this preset no longer exists. It will be applied without a logo.',
            'open_in_builder' => 'Open in builder',
            'manage_in_dashboard' => 'Manage in the dashboard',
        ],

        'modes' => [
            'static' => 'Static',
            'provider' => 'Providers',
            'custom' => 'Custom',
        ],
        'provider' => [
            'sections' => [
                'metric' => 'Metric',
            ],
            'fields' => [
                'provider' => 'Provider',
                'definition' => 'Metric',
            ],
            'invalid_param' => 'Check the parameters and try again.',
            'example' => 'Example: {path}',
            'cache_note' => 'The server controls the cache for provider badges.',
            'preview_invalid' => 'Fill the parameters to preview the badge.',
            'params' => [
                'owner' => 'Owner',
                'repo' => 'Repository',
                'package' => 'Package',
                'vendor' => 'Vendor',
                'namespace' => 'Namespace',
                'repository' => 'Repository',
            ],
            'metrics' => [
                'github_stars' => 'GitHub stars',
                'github_forks' => 'GitHub forks',
                'github_issues' => 'GitHub open issues',
                'github_license' => 'GitHub license',
                'npm_v' => 'npm version',
                'npm_dm' => 'npm monthly downloads',
                'packagist_dt' => 'Packagist downloads',
                'packagist_v' => 'Packagist version',
                'docker_pulls' => 'Docker Hub pulls',
                'codecov_coverage' => 'Codecov coverage',
                'coveralls_coverage' => 'Coveralls coverage',
                'laravel_v' => 'Laravel version',
                'laravel_dt' => 'Laravel downloads',
                'php_v' => 'PHP version',
                'python_v' => 'Python version',
            ],
        ],
        'custom_providers' => [
            'title' => 'Custom providers',
            'description' => 'Badges from any public JSON document you map yourself. The source URL stays on the server; the image uses an opaque identifier.',
            'create_button' => 'New custom provider',
            'readonly_note' => 'Custom providers are available on paid plans. Existing provider badges keep working and can still be copied or deleted.',
            'upgrade' => 'Upgrade',
            'limit_reached' => 'You reached the limit of {limit} custom providers.',
            'empty' => 'You do not have custom providers yet.',
            'disabled' => 'Disabled',
            'copy_svg' => 'Copy SVG',
            'copy_png' => 'Copy PNG',
            'delete_description' => 'The public URL stops working and the cached copy is discarded.',
            'dialog_create_title' => 'Create custom provider',
            'dialog_edit_title' => 'Edit custom provider',
            'dialog_description' => 'Point to a public JSON document and pick the values with JSON Pointer.',
            'template_immutable' => 'The source template is immutable: to change it, delete and create again. Metric slugs cannot be renamed either.',
            'add_metric' => 'Add metric',
            'remove_metric' => 'Remove metric',
            'create_submit' => 'Create provider',
            'update_submit' => 'Save changes',
            'fields' => [
                'name' => 'Name',
                'url_template' => 'Source template',
                'path_params' => 'Path parameters',
                'example_path' => 'Example path',
                'metrics' => 'Metrics',
                'metric_slug' => 'Slug',
                'metric_label' => 'Label',
                'pointer' => 'JSON Pointer',
                'formatter' => 'Format',
            ],
            'hints' => [
                'url_template' => 'HTTPS URL with the placeholders you declare, e.g. https://api.example.com/{\'{\'}owner{\'}\'}/{\'{\'}repo{\'}\'}.',
                'template_note' => 'Path placeholders use {\'{\'}name{\'}\'}. The template is validated when you save.',
                'path_params' => 'Comma-separated placeholders, in path order.',
                'example_path' => 'A real path used to validate the mapping when you save.',
                'pointer' => 'RFC 6901, e.g. /stargazers_count or /items/0/score.',
            ],
            'formatters' => [
                'text' => 'Text',
                'number' => 'Number',
                'percent' => 'Percent',
            ],
            'builder' => [
                'sections' => [
                    'metric' => 'Metric',
                ],
                'fields' => [
                    'provider' => 'Provider',
                    'definition' => 'Metric',
                ],
                'invalid_param' => 'Check the parameters and try again.',
                'example' => 'Example: {path}',
                'cache_note' => 'The server controls the cache for custom provider badges.',
                'preview_invalid' => 'Fill the parameters to preview the badge.',
            ],
        ],
        'personal' => [
            'create_button' => 'Personal badge',
            'title' => 'Personal badges',
            'description' => 'Badges published under your username, ready to embed. Provider badges update automatically.',
            'dialog_title' => 'Create personal badge',
            'dialog_description' => 'Save the current badge under your username and share its URL.',
            'name' => 'Name',
            'name_placeholder' => 'My badge',
            'metric_note' => 'Metric: {metric}',
            'private' => 'Private',
            'private_hint' => 'Requires an access token to read a private repository or package.',
            'credential' => 'Personal access token',
            'credential_placeholder' => 'Never shown again after saving',
            'credential_username' => 'Account username',
            'credential_username_placeholder' => 'Docker username',
            'params_invalid' => 'Fix the parameters before saving.',
            'create' => 'Create badge',
            'creating' => 'Creating...',
            'upgrade_cta' => 'Personal badges are available on paid plans. Upgrade to publish badges under your username.',
            'upgrade' => 'Upgrade',
            'readonly_note' => 'Your plan no longer includes creating personal badges. Existing badges keep working and can still be copied or deleted.',
            'empty' => 'You don\'t have personal badges yet. Create one in the builder.',
            'copy_svg' => 'Copy SVG',
            'copy_png' => 'Copy PNG',
            'refresh' => 'Refresh',
            'refreshing' => 'Refreshing...',
            'refresh_failed' => 'Could not refresh the badge. Try again later.',
            'change_credential' => 'Change token',
            'credential_set' => 'Access token saved',
            'credential_missing' => 'Access token missing',
            'credential_not_required' => 'Public item',
        ],
        'endpoint' => [
            'cta_title' => 'JSON endpoint badge',
            'cta_description' => 'Turn a JSON URL into a badge. Register the endpoint in your account panel; the source URL stays on the server.',
            'cta_action' => 'Manage endpoints',
            'create_button' => 'New endpoint',
            'title' => 'JSON endpoint badges',
            'description' => 'Badges rendered from a JSON URL you register. The source URL is stored on the server and never exposed; the public image uses an opaque identifier.',
            'readonly_note' => 'Verify your email to register endpoints. Existing badges keep working.',
            'empty' => 'You have no JSON endpoint badges yet.',
            'dialog_title' => 'Register JSON endpoint',
            'dialog_description' => 'The endpoint must return the shields JSON contract over HTTPS.',
            'name' => 'Name',
            'name_placeholder' => 'Deploys',
            'url' => 'Source URL',
            'url_placeholder' => 'https://example.com/badge.json',
            'url_hint' => 'HTTPS only, public IP, no redirects. The URL is stored on the server and never returned to the browser.',
            'create' => 'Register endpoint',
            'creating' => 'Registering...',
            'copy_svg' => 'Copy SVG',
            'copy_png' => 'Copy PNG',
            'delete_description' => 'The public URL stops working and the cached copy is discarded. The source URL is not deleted anywhere else.',
        ],
        'social_note' => 'The social style always uses its own colors.',
    ],
];
