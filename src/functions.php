<?
require 'config.php';

checkLeads(getData());

/**
 * Получение данных из формы
 *
 * @return $data_form
 */
function getData()
{
  $data = array();

  foreach ($_POST as $key => $value)
  {
    if($value != '') $data_form[$key] = htmlspecialchars($value);
  }

  return $data_form;
}

/**
 * Проверка существования в crm лида с номером телефона из формы
 *
 * @param $data_form
 */
function checkLeads($data_form)
{
  $phone = $data_form['phone'];
  $url = 'https://crm.zoho.eu/crm/v2/Leads?fields=Phone';

  $response = execute($url);

  if($response != NULL)
  {
    foreach ($response->data as $key => $value)
    {
      if(convertPhone($value->Phone) == convertPhone($phone))
      {
        $id = $value->id;
      }
    }
  }

  // Если лид с таким номер телефона существует в crm, конвертируем его
  // $data_form['amount'] - сумма сделки
  if(isset($id)) convertLead($id, $data_form);
  // Иначе добавляем новый лид
  else createLead($data_form);
}

/**
 * Удаляем из номера телефона всё лишнее
 *
 * @param $phone
 *
 * @return $phone
 */
function convertPhone($phone)
{
  $phone = strval($phone);
  $phone = preg_replace('~[^0-9]+~','',$phone);
  $phone = substr($phone, 1);

  return $phone;
}

/**
 * Добавление нового лида в crm
 *
 * @param $data_form
 */
function createLead($data_form)
{
  $url = 'https://crm.zoho.eu/crm/v2/Leads';

  // Данные о новом лиде из формы
  $arr = array (
    'data' =>
      array (
        0 =>
          array (
            'First_Name' => $data_form['firstname'],
            'Last_Name' => $data_form['lastname'],
            'Company' => $data_form['company'],
            'Phone' => $data_form['phone'],
            'Email' => $data_form['email'],
            'Lead_Source' => $data_form['source']
          )
    )
  );

  $response = execute($url, 'post', $arr);
  showInfo(getStatus($response), 'Создание');
}

/**
 * Конвертация лида
 *
 * @param $id, $data_form
 */
function convertLead($id, $data_form)
{
  $url = 'https://crm.zoho.eu/crm/v2/Leads/'.$id.'/actions/convert';

  // Данные для конвертации лида
  $arr = array (
    'data' =>
      array (
        0 =>
          array (
            'overwrite' => true,
              'Deals' =>
                array (
                  'Deal_Name' => $data_form['firstname'] . ' ' .
                                 $data_form['lastname'] . ' - ' .
                                 $data_form['company'],
                  'Closing_Date' => date('Y-m-d'),
                  'Stage' => 'Оценка пригодности',
                  'Amount' => intval($data_form['amount'])
                ),
          ),
      ),
    );

  $response = execute($url, 'post', $arr);
  showInfo(getStatus($response), 'Преобразование');
}

/**
 * Получение статуса о выполнении запроса
 *
 * @param $response
 *
 * @return $status
 */
function getStatus($response)
{
  foreach ($response->data as $key => $value)
  {
    $status = $value->code;
  }
  return $status;
}

/**
 * Вывод на экран информации о выполнении запроса (успешно/неуспешно)
 *
 * @param $status, $text
 */
function showInfo($status, $text)
{
  if($status != 'SUCCESS' && $status != NULL)
    echo $text . " лида прошло не успешно. Попробуйте ещё раз.<br />
          <a href='/'>Вернуться на главную</a>";
  else
    echo $text . " лида прошло успешно.<br />
          <a href='/'>Вернуться на главную</a>";
}

/**
 * Получение нового токена
 */
function refreshToken()
{
  $curl = curl_init();
  curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://accounts.zoho.eu/oauth/v2/token',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => http_build_query($_SESSION['refresh_info'])
  ));

  $response = json_decode(curl_exec($curl));
  curl_close($curl);
  $_SESSION['access_token'] = $response->access_token;
}

/**
 * Выполнение запроса
 *
 * @param $url, $method = null, $params = null
 *
 * @return $response
 */
function execute($url, $method = null, $params = null)
{
  $headers = array("Authorization: Zoho-oauthtoken " . $_SESSION['access_token']);

  $curl = curl_init();
  curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true
  ));

  // Если необходим post-запрос, добавляем соответсвующие параметры
  // (CURLOPT_POST, CURLOPT_POSTFIELDS)
  if(isset($method) == 'post')
    curl_setopt($curl, CURLOPT_POST, true);
  if(isset($params))
  {
    $params = json_encode($params);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
    $headers = array('Content-Type:application/json',
                     'Content-Length: ' . strlen($params),
                     'Authorization: Zoho-oauthtoken ' . $_SESSION['access_token']
               );
  }

  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

  $response = json_decode(curl_exec($curl));
  curl_close($curl);

  $response = checkResponse($response, $url);
  return $response;
}

/**
 * Проверка выолнения запроса
 *
 * @param $response, $url
 *
 * @return $response
 */
function checkResponse($response, $url)
{
  // Если при выполнении запроса срок действия токена истёк,
  // получаем новый токен и заново выполням запрос
  if($response->code == 'INVALID_TOKEN')
  {
    refreshToken();
    $response = execute($url);
  }

  return $response;
}
