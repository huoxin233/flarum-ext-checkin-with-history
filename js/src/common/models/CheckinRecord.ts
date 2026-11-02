import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';

export default class CheckinRecord extends Model {
  date = Model.attribute<string>('date');
  start = Model.attribute<string>('start');
  color = Model.attribute<string>('color');
  type = Model.attribute<number>('type');
  streak = Model.attribute<number>('streak');
  checkedInAt = Model.attribute<string>('checkedInAt');
  time = Model.attribute<string>('time');
  user = Model.hasOne<User>('user');
}
