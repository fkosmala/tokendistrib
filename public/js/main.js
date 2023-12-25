$(function() {
    function calcShares() {
        $('.included').each(function () {
            hp = $(this).text();
            total = $('#totalHP').text();
            percent = parseFloat((hp / total)*100);
            $(this).next('.accShare').text(percent);
        });
    }

    function calcTotalHP() {
        let totalHP = 0;
        $('.included').each(function() {
            value = parseFloat($(this).text());
            totalHP = totalHP + value;
        });
        $('#totalHP').text(totalHP.toFixed(3));
    }

    $("#findAccount").on("click tap", function(e) {
        e.preventDefault();
        $("#findAccount").prop('disabled', true);
        let account = $("#account").val();
        url ="/account/" + account;
        $.get(url, function(data) {
            $('#accountSearch').slideUp();
            let tbody = $('#tbodyDeleg');
            tbody.html('');
            $.each(data, function(i, item) {
                tbody.append('\
                    <tr>\
                        <td>\
                            <select class="unlist">\
                                <option value="listed" selected>Listed</option>\
                                <option value="redist">Redistribution</option>\
                                <option value="hold">Hold Rewards</option>\
                            </select>\
                        </td>\
                        <td class="accName">'+item['account']+'</td>\
                        <td class="accHP listed included">'+item['hp']+'</td>\
                        <td class="accShare"></td>\
                    </tr>\
                ');
            });

            //Calculate the total HPs
            calcTotalHP();
            $('#totalHPDisplayed').text($('#totalHP').text());

            // Calculate shares in %
            calcShares();

            $('.unlist').on('change', function() {
                line = $(this).parents('tr');
                rewardType = $(this).val();
                console.log(rewardType);
                if (rewardType === "redist") {
                    line.find('.accHP').removeClass('listed').removeClass('included');
                    line.find('.accShare').text(0);
                } else if (rewardType === "hold") {
                    line.find('.accHP').removeClass('listed').addClass('included');
                } else {
                    line.find('.accHP').addClass('listed').addClass('included');
                }
                calcTotalHP();
                calcShares();
            });

            $('#delegatees').slideDown();
            $("html, body").animate({ scrollTop: 0 });

            //$("#findAccount").prop('disabled', false);
        });
    });

    $('#sameAccount').on('click tap', function() {
        if ($('#sameAccount').is(':checked')) {
            sender = $('#account').val();
            $('#sendAccount').val(sender);
            $('#sendAccount').prop('disabled', true);
        } else {
            $('#sendAccount').val('');
            $('#sendAccount').prop('disabled', false);
        }
    });

    $('#searchTokens').on('click tap', function(e) {
        e.preventDefault();
        $('#searchTokens').prop('disabled', true);
        tokensAccount = $('#sendAccount').val();
        urlTokens ="/tokens/" + tokensAccount;

        $.get(urlTokens, function(data) {
            $('#delegatees').slideUp();
            let tbody = $('#tbodyTokens');
            tbody.html('');
            $.each(data, function(i, item) {
                tbody.append('\
                    <tr>\
                        <td><input type="radio" name="token" value="'+item['symbol']+'"></td>\
                        <td class="tokenSymbol">'+item['symbol']+'</td>\
                        <td class="tokenBalance">'+item['balance']+'</td>\
                    </tr>\
                ');
            });
            $('#searchTokens').prop('disabled', false);
            $('#createQuery').removeClass('hide');
            $('#tokens').slideDown();
            $("html, body").animate({ scrollTop: 0 });
        });

        $('#createQuery').on('click tap', function() {
            $('#createQuery').prop('disabled', true);
            selectedToken = $("input[name='token']:checked").val();
            valueToken = parseFloat($("input[name='token']:checked").parents('tr').find('.tokenBalance').text());
            sendValue = 0;
            let tbody = $('#tbodyQuery');
            tbody.html('');
            json_payload = []
            $('.listed').each(function() {
                account = $(this).parents('tr').find('.accName').text();
                share = $(this).parents('tr').find('.accShare').text();
                accValue = (share/100)*valueToken; 
                if (accValue < 0.01) {
                    finalVal = parseFloat((accValue*100) / 100).toFixed(8);
                } else {
                    finalVal = (Math.floor(accValue*100) / 100);
                }
                sendValue = sendValue + finalVal;
                sender = $('#sendAccount').val();
                json_payload.push({
                        'contractName': 'tokens',
                        'contractAction': 'transfer',
                        'contractPayload': {
                        'symbol': selectedToken,
                        'to': account,
                        'quantity': String(finalVal),
                        'memo': 'Rewards from '+sender
                    }
                });
                tbody.append('\
                    <tr>\
                        <td class="rewardsAccount">'+account+'</td>\
                        <td class="rewardsValue">'+finalVal+'</td>\
                    </tr>\
                ');
            });
            json_payload = JSON.stringify(json_payload);
            $('#tokens').slideUp()
            $('#sendQuery').removeClass('hide').val("Send "+sendValue.toFixed(2)+" tokens!");
            $('#query').slideDown();
            $("html, body").animate({ scrollTop: 0 });
        });

        $('#sendQuery').on('click tap', function() {
            if (typeof hive_keychain === 'object') {
                hive_keychain.requestCustomJson(
                    sender,
                    'ssc-mainnet-hive',
                    'Active',
                    json_payload,
                    'Token Distribution',
                    function(response) {
                        $('#finalResult').removeClass('hide');
                        if (!response['success']) {
                            $('#finalResult').text(response.message)
                        } else {
                            $('#finalResult').html('<h2>Tokens successfully sended</h2>');
                        }
                    }
                )
            } else {
                network = "ssc-mainnet-hive"
                signerUrl = 'https://hivesigner.com/sign/custom-json?required_auths=["'+account+'"]&id='+network+'&authority=active&required_posting_auths=[]&json='+json_payload
                window.location.href = signerUrl;
            }
        });
    });
});