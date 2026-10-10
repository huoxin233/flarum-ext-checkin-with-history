import app from 'flarum/forum/app';
import CheckinRecord from '../common/models/CheckinRecord';
import addCheckinButton from './addCheckinButton';
import extendUserProfile from './extendUserProfile';

app.initializers.add('primexcalibur-checkin-with-history', () => {
  app.store.models['checkin/history'] = CheckinRecord;
  addCheckinButton();
  extendUserProfile();
});
