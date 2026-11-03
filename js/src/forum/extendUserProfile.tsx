import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserPage from 'flarum/forum/components/UserPage';
import LinkButton from 'flarum/common/components/LinkButton';
import CheckinHistoryPage from './pages/CheckinHistoryPage';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

export default function extendUserProfile(): void {
  app.routes['user.checkin.history'] = {
    path: '/u/:username/checkin/history',
    component: CheckinHistoryPage,
  };

  extend(UserPage.prototype, 'navItems', function (items: ItemList<Mithril.Children>) {
    if (!this.user) {
      return;
    }

    const isSelf = !!(app.session.user && app.session.user.id() === this.user.id());
    const canViewOthers = Boolean(app.forum.attribute('canQueryOthersHistory'));

    if (!isSelf && !canViewOthers) {
      return;
    }

    items.add(
      'post-checkin-history',
      <LinkButton
        href={app.route('user.checkin.history', {
          username: this.user.slug(),
        })}
        icon="fas fa-calendar-alt"
      >
        {app.translator.trans('primexcalibur-checkin-with-history.forum.page.link-name')}
      </LinkButton>
    );
  });
}
