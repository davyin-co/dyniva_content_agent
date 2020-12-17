<?php
/**
 * Implements hook_drush_command().
 */
function dyniva_content_agent_drush_command(){
  $items = [];
  // $items['sync-master'] = array(
  //   'description' => "同步单个entity",
  //   'arguments' => array(
  //     'bundle' => 'The entity bundle',
  //     'id' => 'The entity id',
  //   ),
  //   'examples' => array(
  //     'drush agent:sync-master node 12' => 'Triggers a sync',
  //   ),
  //   'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
  //   'drupal dependencies' => array(
  //     'dyniva_core'
  //   )
  // );
  $items['sync-master-test'] = array(
    'description' => "test case: sync to master",
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL
  );
  return $items;
}

function drush_dyniva_content_agent_sync_master($bundle='node', $id=0) {
  if($id <= 0) {
    drush_set_error('缺少参数 例子：drush sync-master node 12');
    return;
  }

  /* @var \Drupal\dyniva_content_agent\Manager $manager */
  $manager = \Drupal::service('dyniva_content_agent.manager');
  $entity = \Drupal::service('entity.manager')->getStorage($bundle)->load($id);
  if(!$entity) {
    drush_set_error('找不到指定entity');
    return;
  }
  $manager->doSync([$entity->uuid()]);
}

function drush_dyniva_content_agent_sync_master_test() {
  $config = \Drupal::config('dyniva_content_agent.settings');
  $server_url = $config->get('server_domain');
  $site_id = $config->get('site_id');
  $nodeStorage = \Drupal::service('entity.manager')->getStorage('node');
  $dateFormatter = \Drupal::service('date.formatter');
  $node = $nodeStorage->create([
    'type' => 'article',
    'title' => 'sync test: import a node '.$dateFormatter->format(time(), 'custom', 'Y.m.d H:i:s'),
    'uid' => 1,
    'status' => TRUE,
    'body' => [
      'value' => 'test content',
      'format' => 'rich_text'
    ]
  ]);
  $node->save();
  drush_print($node->uuid());

  $client = \Drupal::service('dyniva_content_receiver.client');
  $queue = \Drupal::service('dyniva_content_receiver.queue');
  $queue->insert($server_url, $node, ['Authorization' => "Uuid " . $site_id]);

  foreach($queue->getItems(10) as $row) {
    list($url, $entity, $headers) = $queue->getInfo($row->id);

    drush_print($url);
    drush_print(print_r($headers, true));

    if($url && $entity && $client->doSyncEntity($url, $entity, $headers)) {
      $queue->update($row->id, [
        'changed' => time(),
        'status' => 1
      ]);
      // Delete node
      $node = node_load($node->id());
      $node->delete();
      $nodeStorage->purge([$node]);

      drush_print('OK');
    } else {
      drush_print('error');
    }
  }

}
