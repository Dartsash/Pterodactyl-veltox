Veltox redesign patch

Apply from your panel root (/var/www/pterodactyl):
  unzip -o veltox-redesign.zip
  yarn build:production
  php artisan view:clear
  (then hard-refresh: Ctrl+F5)

Changed files:
[theme]
- tailwind.config.js                       dark Aviary palette, blue accent, status colors
[footer]
- .../elements/PageContentBlock.tsx        footer -> Veltox
[auth - dark]
- .../auth/LoginFormContainer.tsx          dark card + footer
- .../auth/LoginContainer.tsx              dark login fields
- .../auth/ForgotPasswordContainer.tsx     dark field
- .../auth/LoginCheckpointContainer.tsx    dark 2FA field
- .../auth/ResetPasswordContainer.tsx      dark reset fields
[navigation]
- .../NavigationBar.tsx                    visible hover + blue active underline
- .../elements/SubNavigation.tsx           tab hover + blue active underline
[console + status]
- .../server/console/Console.tsx           prompt -> container@veltox~
- .../server/console/style.module.css      framed terminal + blue command focus
- .../server/console/ServerDetailsBlock.tsx  status colors: green/orange/red
