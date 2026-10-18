import app from 'flarum/admin/app';
import CheckinSettingsPage from './components/CheckinSettingsPage';

app.initializers.add('primexcalibur-checkin-with-history', () => {
  app.extensionData
    .for('primexcalibur-checkin-with-history')
    .registerPage(CheckinSettingsPage)
    .registerPermission(
      {
        icon: 'fas fa-calendar-check',
        label: app.translator.trans('primexcalibur-checkin-with-history.admin.settings.allow-check-in'),
        permission: 'checkin.allowCheckIn',
      },
      'moderate',
      90
    )
    .registerPermission(
      {
        icon: 'fas fa-id-card',
        label: app.translator.trans('primexcalibur-checkin-with-history.admin.settings.allow-supplementary-check-in'),
        permission: 'checkin.allowSupplementaryCheckIn',
      },
      'moderate',
      90
    )
    .registerPermission(
      {
        icon: 'fas fa-id-card',
        label: app.translator.trans('primexcalibur-checkin-with-history.admin.settings.query-others-history'),
        permission: 'checkin.queryOthersHistory',
        allowGuest: true,
      },
      'view'
    );
});
