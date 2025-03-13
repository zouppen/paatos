# paatos

Document generator for meeting minutes

## Requirements

Way to steal cookies from a browser. For Firefox, install
[cookies.txt Firefox add-on](https://addons.mozilla.org/fi/firefox/addon/cookies-txt).

### Fedora

```sh
sudo dnf install pandoc php-cli php-xml texlive-multirow 
```

### Debian

```sh
sudo apt install pandoc php-cli php-xml texlive-latex-extra php-curl
```

TODO Verify if there are additional requirements

### Docker and Podman

TODO

## Usage

1. Steal cookies from your browser and place them to `cookies.txt`
in this directory. Use a browser add-on instructed above.
2. Run `./render-minutes URL` where URL is a link to the document page (internal wiki)
3. Document is produced. Enjoy!

## License

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or (at
your option) any later version.

NB! The branding in individual templates (e.g. `logo.pdf` under
`templates` directory) are freely distributable but **not**
necessarily licenced under the terms of GNU GPL.
