import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';

export default class CheckinRecord extends Model {
  date = Model.attribute<string>('start');
  color = Model.attribute<string>('color');
  type = Model.attribute<number>('type');
  user = Model.hasOne<User>('user');
}
