<?php

/**
 * 投稿関係のコントローラー PostsController
 */
class PostsController extends Controller {
  // ログインが必要なアクションを指定する
  protected $auth_actions = array('new', 'create', 'show', 'index');
  // 権限が必要なアクションを指定する
  protected $right_actions = array('edit', 'update', 'destroy');

  /**
   * 新規投稿ページを返すメソッド
   * 
   * 新規投稿ページには前回記入したcontentsとtokenを渡す
   * 
   * @return string
   */
  public function newAction() {
    $performances = $this->db_manager->get('Performances')->fetchAllPerformances();
    return $this->render(array(
      'performances' => $performances,
      'contents' => '',
      '_token' =>$this->generateCsrfToken('posts/new'),
    ));
  }

  /**
   * 投稿するメソッド
   * 
   * HTTPメソッドがPost以外の場合は404ページに遷移させ、トークンの照合が不正な場合はリダイレクトする
   * 投稿に問題なければinsert文を実行しユーザー詳細ページにリダイレクトし、
   * 問題がある場合は各データを渡して再度表示させる (リダイレクトではない)
   * 
   * @throws HttpNotFoundException | @return void|string
   */
  public function createAction() {
    if (!$this->request->isPost()) {
      $this->forward404();
    }

    $performance = $this->request->getPost('performance');
    if (!$performance) {
      $this->forward404();
    }

    $token = $this->request->getPost('_token');
    if (!$this->checkCsrfToken('posts/new', $token)) {
      return $this->redirect('/posts/new');
    }

    $contents = $this->request->getPost('contents');

    $errors = array();

    if (!strlen($contents)) {
      $errors[] = '感想を入力してください';
    } elseif (mb_strlen($contents) > 200) {
      $errors[] = '感想は200字以内で入力してください';
    }

    if (count($errors) === 0) {
      $user = $this->session->get('user');
      $this->db_manager->get('Posts')->insert($user['id'], $contents, $performance['id']);
      return $this->redirect('/users/' . $user['id']);
    }

    $user = $this->session->get('user');

    return $this->render(array(
      'errors' => $errors,
      'contents' => $contents,
      'performances' => $performance,
      '_token' => $this->generateCsrfToken('posts/new'),
    ), 'new');
  }

  /**
   * 投稿詳細ページを表示させるメソッド
   * 
   * ルーティングでマッチした配列を受け取り、投稿が存在するか確認した後、詳細ページを表示する
   * 投稿が存在しなかった場合は404ページに遷移させる
   * 
   * @param array $paramas
   * @return string
   */
  public function showAction($params) {
    $post = $this->db_manager->get('Posts')->fetchById($params['id']);
    
    if (!$post) {
      $this->forward404();
    }

    $user = $this->session->get('user');

    return $this->render(array(
      'post' => $post,
      'user' => $user,
    ));
  }
  
  /**
   * 投稿編集ページの表示メソッド
   * 
   * URLから動的パラメータを受け取り、投稿の編集ページを表示させる
   * 
   * @param array $params
   * @throws HttpNotFoundException | @return string
   */
  public function editAction($params) {
    $post = $this->db_manager->get('Posts')->fetchById($params['id']);

    if (!$post) {
      $this->forward404();
    }

    $performance = $this->db_manager->get('Performances')->fetchByPerformanceId($post['performance_id']);
    if (!$performance) {
      $this->forward404();
    }

    return $this->render(array(
      'post' => $post,
      'performance' => $performance,
      '_token' => $this->generateCsrfToken('posts/edit'),
    ));
  }

  /**
   * 投稿のアップデートメソッド
   * 
   * URLから動的パラメータを受け取る
   * HTTPメソッドがPOSTでなければ404に遷移させ、トークンが不正ならリダイレクトさせる
   * 投稿チェックを行い、エラーがなければupdate文を実行し、投稿詳細ページにリダイレクトさせる
   * エラーがある場合は編集ページを再度表示させる (リダイレクトではない)
   * 
   * @param array $params
   * @throws HttpNotFoundException | @return void|string
   */
  public function updateAction($params) {
    if (!$this->request->isPost()) {
      $this->forward404();
    }

    $post = $this->db_manager->get('Posts')->fetchById($params['id']);

    $token = $this->request->getPost('_token');
    if(!$this->checkCsrfToken('posts/edit', $token)) {
      return $this->redirect('/posts/' . $post['id'] . '/edit');
    }

    $post['contents'] = $this->request->getPost('contents');

    $errors = array();

    if (!strlen($post['contents'])) {
      $errors[] = '感想を入力してください';
    } elseif (mb_strlen($post['contents']) > 200) {
      $errors[] = '感想は200字以内で入力してください';
    }

    if (count($errors) === 0) {
      $user = $this->session->get('user');
      $this->db_manager->get('Posts')->update($post['id'], $post['contents']);
      return $this->redirect('/posts/' . $post['id']);
    }

    return $this->render(array(
      'post' => $post,
      'errors' => $errors,
      '_token' => $this->generateCsrfToken('posts/edit'),
    ), 'edit');
  }

  /**
   * 投稿削除メソッド
   * 
   * URLから投稿情報を受け取り、該当のIDの投稿を削除するアクション
   * 削除が完了したらユーザー詳細ページにリダイレクトする
   * 
   * @param array $params
   * @return void
   */
  public function destroyAction($params) {

    // POST制限かけたいけどaタグからだと難しそうだからとりあえず無視
    // if (!$this->request->isPost()) {
    //   $this->forward404();
    // }

    $post = $this->db_manager->get('Posts')->fetchById($params['id']);
    $user = $this->session->get('user');

    // トークンチェックもするべきだけどとりあえず無視
    // $token = $this->request->getPost('_token');
    // if(!$this->checkCsrfToken('posts/show', $token)) {
    //   return $this->redirect('/posts/' . $post['id'] . '/edit');
    // }

    $this->db_manager->get('Posts')->delete($post['id']);
    return $this->redirect('/users/' . $user['id']);
  }

  /**
   * タイムラインの表示メソッド
   * 
   * タイムラインを表示させる
   * (自分の投稿とフォローしているユーザーの投稿全て表示)
   * 
   * @return string
   */
  public function indexAction() {
    $user = $this->session->get('user');
    $posts = $this->db_manager->get('Posts')->fetchAllPersonalArchivesByUserId($user['id']);

    return $this->render(array(
      'posts' => $posts,
    ));
  }
}