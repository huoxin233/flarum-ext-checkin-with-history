import app from 'flarum/admin/app';
import CheckinHistorySettingsPage from './components/CheckinHistorySettingsPage';

app.initializers.add('mattoid-daily-check-in-history', () => {
  app.extensionData
    .for('primexcalibur-checkin-with-history')
    .registerPage(CheckinHistorySettingsPage)
    .registerPermission(
      {
        icon: 'fas fa-id-card',
        label: app.translator.trans('mattoid-daily-check-in-history.admin.settings.allow-supplementary-check-in'),
        permission: 'checkin.allowSupplementaryCheckIn',
      },
      'moderate',
      90
    )
    .registerPermission(
      {
        icon: 'fas fa-id-card',
        label: app.translator.trans('mattoid-daily-check-in-history.admin.settings.issuance-of-supplementary-cards'),
        permission: 'checkin.issuanceOfSupplementaryCards',
      },
      'moderate',
      90
    )
    .registerPermission(
      {
        icon: 'fas fa-id-card',
        label: app.translator.trans('mattoid-daily-check-in-history.admin.settings.query-others-history'),
        permission: 'checkin.queryOthersHistory',
        allowGuest: true,
      },
      'view'
    );
});
