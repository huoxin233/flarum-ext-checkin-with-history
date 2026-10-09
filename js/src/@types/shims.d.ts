import 'flarum/common/models/User';
import 'flarum/forum/components/UserPage';
import 'flarum/forum/components/UserCard';

declare module 'flarum/common/models/User' {
  export default interface User {
    checkinCard(): number | undefined;
    canQueryOthersHistory(): boolean | undefined;
  }
}

declare module 'flarum/forum/components/UserPage' {
  export default interface UserPage {
    user: any;
    loading: boolean;
    loadUser(username: string): any;
    show(user: any): void;
  }
}

declare module 'flarum/forum/components/UserCard' {
  export default interface UserCard {
    attrs: {
      user?: any;
      [key: string]: any;
    };
  }
}
